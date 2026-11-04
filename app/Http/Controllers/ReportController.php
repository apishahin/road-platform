<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Services\ReportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use RuntimeException;

class ReportController extends Controller
{
    public function __construct(private ReportService $reports)
    {
    }

    public function create()
    {
        $map    = (array) config('roadplatform.map', []);
        $center = is_array($map['center'] ?? null) ? $map['center'] : [];

        return view('reports.create', [
            'categories'  => Category::orderBy('id')->get()->map(fn ($c) => ['id' => $c->id, 'label' => $c->name()])->all(),
            'center'      => [
                (float) ($center[0] ?? $center['lat'] ?? 23.8103),
                (float) ($center[1] ?? $center['lng'] ?? $center['lon'] ?? 90.4125),
            ],
            'zoom'        => (int) ($map['zoom'] ?? 12),
            'tileUrl'     => (string) ($map['tile_url'] ?? 'https://tile.openstreetmap.org/{z}/{x}/{y}.png'),
            'attribution' => (string) ($map['attribution'] ?? '© OpenStreetMap contributors'),
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
            'lat'         => ['required', 'numeric', 'between:20.5,26.7'],
            'lng'         => ['required', 'numeric', 'between:88,92.8'],
            'issue_id'    => ['nullable', 'string', 'size:26'],
            'category_id' => ['required_without:issue_id', 'nullable', 'exists:categories,id'],
            'title'       => ['required_without:issue_id', 'nullable', 'string', 'max:150'],
            'description' => ['required', 'string', 'max:1000'],
            'road_name'   => ['nullable', 'string', 'max:150'],
            'landmark'    => ['nullable', 'string', 'max:150'],
            'photos'      => ['nullable', 'array', 'max:3'],
            'photos.*'    => ['file', 'mimes:jpeg,png,webp', 'max:20480'],
        ]);

        try {
            $this->reports->submit(
                user: $user,
                lat: (float) $data['lat'],
                lng: (float) $data['lng'],
                categoryId: isset($data['category_id']) ? (int) $data['category_id'] : null,
                title: isset($data['title']) ? trim($data['title']) : null,
                description: trim($data['description']),
                roadName: isset($data['road_name']) ? trim($data['road_name']) : null,
                landmark: isset($data['landmark']) ? trim($data['landmark']) : null,
                issuePublicId: $data['issue_id'] ?? null,
                files: $request->file('photos', []),
            );
        } catch (RuntimeException $e) {
            return back()->withInput()->withErrors(['photos' => $e->getMessage()]);
        }

        RateLimiter::hit($key, 3600);

        return redirect()->route('report.success')->with('submitted', true);
    }

    public function success()
    {
        abort_unless(session('submitted'), 404);

        return view('reports.success');
    }
}