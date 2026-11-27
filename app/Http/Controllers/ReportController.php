<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Services\ReportService;
use App\Support\AiProviderSettings;
use App\Support\BangladeshMap;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class ReportController extends Controller
{
    public function __construct(private ReportService $reports)
    {
    }

    public function create()
    {
        $map = (array) config('roadplatform.map', []);
        $center = is_array($map['center'] ?? null) ? $map['center'] : [];
        $locale = app()->getLocale();

        // Only active reportable leaves are exposed to citizens.  The parent
        // grouping is data-driven, so an administrator can add a new civic
        // domain without a reporting-form code change.
        $roots = Category::query()
            ->whereNull('parent_id')
            ->where('is_active', true)
            ->with(['children' => fn ($q) => $q->reportable()])
            ->orderBy('sort')->orderBy('id')->get();
        $taxonomy = $roots->map(function (Category $root) {
            $children = $root->children->map(fn (Category $leaf) => [
                'id' => $leaf->id,
                'label' => $leaf->name(),
            ])->values()->all();
            if (! $children && $root->is_reportable) {
                $children[] = ['id' => $root->id, 'label' => $root->name()];
            }

            return ['id' => $root->id, 'label' => $root->name(), 'children' => $children];
        })->filter(fn (array $row) => $row['children'] !== [])->values()->all();

        // Defensive fallback for a pre-upgrade/manual category inserted with
        // no parent. It remains reportable instead of becoming invisible.
        $covered = collect($taxonomy)->pluck('children')->flatten(1)->pluck('id')->all();
        $orphans = Category::reportable()->whereNotIn('id', $covered)->orderBy('sort')->get();
        foreach ($orphans as $leaf) {
            $taxonomy[] = [
                'id' => 'orphan-' . $leaf->id,
                'label' => $leaf->name(),
                'children' => [['id' => $leaf->id, 'label' => $leaf->name()]],
            ];
        }

        return view('reports.create', [
            'taxonomy' => $taxonomy,
            'aiEnabled' => AiProviderSettings::available(),
            'center' => [
                (float) ($center[0] ?? $center['lat'] ?? 23.8103),
                (float) ($center[1] ?? $center['lng'] ?? $center['lon'] ?? 90.4125),
            ],
            'zoom' => (int) ($map['zoom'] ?? 12),
            'tileUrl' => (string) ($map['tile_url'] ?? 'https://tile.openstreetmap.org/{z}/{x}/{y}.png'),
            'attribution' => (string) ($map['attribution'] ?? '© OpenStreetMap contributors'),
            'locale' => $locale,
        ]);
    }

    public function store(Request $request)
    {
        $user = $request->user();
        abort_if($user->is_banned, 403);

        $key = 'report-user:' . $user->id;
        if (RateLimiter::tooManyAttempts($key, 5)) {
            return back()->withInput()->withErrors(['rate' => __('report.too_many')]);
        }

        $data = $request->validate([
            'location_precision' => ['nullable', 'in:exact,approximate,text_only'],
            'lat' => ['nullable', 'numeric', 'between:20.60,26.65'],
            'lng' => ['nullable', 'numeric', 'between:88.00,92.72'],
            'place_name' => ['nullable', 'string', 'max:150'],
            'location_note' => ['nullable', 'string', 'max:500'],
            'issue_id' => ['nullable', 'string', 'size:26'],
            'category_id' => ['required_without:issue_id', 'nullable', 'exists:categories,id'],
            // A short title is required for a new issue; notes attached to an
            // existing issue retain their optional-title behaviour. Description
            // remains optional and is limited below by a word-count rule.
            'title' => ['required_without:issue_id', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:2000'],

            'road_name' => ['nullable', 'string', 'max:150'],
            'landmark' => ['nullable', 'string', 'max:150'],
            'suggested_area_id' => ['nullable', 'integer', 'exists:admin_areas,id'],
            'photos' => ['nullable', 'array', 'max:3'],
            'photos.*' => ['file', 'mimes:jpeg,png,webp', 'max:20480'],
        ]);

        $description = trim((string) ($data['description'] ?? ''));
        if ($this->wordCount($description) > 150) {
            throw ValidationException::withMessages(['description' => __('report.description_word_limit', ['count' => 150])]);
        }

        $precision = $data['location_precision'] ?? 'exact';
        $hasLat = array_key_exists('lat', $data) && $data['lat'] !== null;
        $hasLng = array_key_exists('lng', $data) && $data['lng'] !== null;

        if ($precision === 'text_only') {
            if (empty(trim((string) ($data['place_name'] ?? '')))) {
                throw ValidationException::withMessages(['place_name' => __('report.place_required')]);
            }
            if (! empty($data['issue_id'])) {
                throw ValidationException::withMessages(['issue_id' => __('report.text_only_new_issue')]);
            }
            $lat = $lng = null;
        } else {
            if (! $hasLat || ! $hasLng) {
                throw ValidationException::withMessages(['lat' => __('report.pick_point')]);
            }
            $lat = (float) $data['lat'];
            $lng = (float) $data['lng'];
            if (! BangladeshMap::contains($lat, $lng)) {
                throw ValidationException::withMessages(['lat' => __('report.outside_bangladesh')]);
            }
        }

        if (empty($data['issue_id'])) {
            $category = Category::query()->reportable()->find($data['category_id'] ?? null);
            if (! $category) {
                throw ValidationException::withMessages(['category_id' => __('report.category_unavailable')]);
            }
        }

        try {
            $report = $this->reports->submit(
                user: $user,
                lat: $lat,
                lng: $lng,
                locationPrecision: $precision,
                placeName: isset($data['place_name']) ? trim($data['place_name']) : null,
                locationNote: isset($data['location_note']) ? trim($data['location_note']) : null,
                categoryId: isset($data['category_id']) ? (int) $data['category_id'] : null,
                title: isset($data['title']) ? trim($data['title']) : null,
                description: $description,
                roadName: isset($data['road_name']) ? trim($data['road_name']) : null,
                landmark: isset($data['landmark']) ? trim($data['landmark']) : null,
                issuePublicId: $data['issue_id'] ?? null,
                suggestedAreaId: $precision === 'text_only' ? null : (isset($data['suggested_area_id']) ? (int) $data['suggested_area_id'] : null),
                files: $request->file('photos', []),
            );
        } catch (RuntimeException $e) {
            return back()->withInput()->withErrors(['photos' => $e->getMessage()]);
        }

        try {
            if ($report->issue_id) {
                \App\Models\IssueFollow::firstOrCreate(
                    ['issue_id' => $report->issue_id, 'user_id' => $user->id],
                    ['created_at' => now()]
                );
            }
        } catch (\Throwable) {
            // Following is intentionally best-effort.
        }

        RateLimiter::hit($key, 3600);

        return redirect()->route('report.success')->with('submitted', true)
            ->with('text_only_location', $precision === 'text_only');
    }

    private function wordCount(string $value): int
    {
        $value = trim($value);
        return $value === '' ? 0 : count(preg_split('/\s+/u', $value, -1, PREG_SPLIT_NO_EMPTY));
    }

    public function success()
    {
        abort_unless(session('submitted'), 404);

        return view('reports.success');
    }
}
