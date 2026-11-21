<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Issue extends Model
{
    use HasFactory;

    protected $fillable = [
        'public_id',          // ← added
        'title', 'category_id', 'lat', 'lng', 'road_name', 'landmark',
        'admin_area_id', 'progress_status', 'verification_status',
        'disposition', 'duplicate_of_id', 'reports_count',
        'first_reported_at', 'last_activity_at',
    ];

    // Auto-generate a ULID if not provided
    protected static function booted(): void
    {
        static::creating(function (Issue $issue) {
            if (empty($issue->public_id)) {
                $issue->public_id = (string) Str::ulid();
            }
        });
    }

    protected function casts(): array
    {
        return [
            'lat'               => 'decimal:7',
            'lng'               => 'decimal:7',
            'reports_count'     => 'integer',
            'first_reported_at' => 'datetime',
            'last_activity_at'  => 'datetime',
            // Work Center and administration display these lifecycle moments.
            'published_at'      => 'datetime',
            'unpublished_at'    => 'datetime',
        ];
    }

    // ── Relationships ──────────────────────────────────

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function adminArea(): BelongsTo
    {
        return $this->belongsTo(AdminArea::class);
    }

    public function duplicateOf(): BelongsTo
    {
        return $this->belongsTo(Issue::class, 'duplicate_of_id');
    }

    public function duplicateChildren(): HasMany
    {
        return $this->hasMany(Issue::class, 'duplicate_of_id');
    }

    public function reports(): HasMany
    {
        return $this->hasMany(Report::class)->latest('created_at');
    }

    public function statusEvents(): HasMany
    {
        return $this->hasMany(StatusEvent::class)->latest('created_at');
    }

    public function observations(): HasMany
    {
        return $this->hasMany(Observation::class)->latest('created_at');
    }

    public function evidence(): HasManyThrough
    {
        return $this->hasManyThrough(Evidence::class, Report::class, 'issue_id', 'report_id');
    }

    public function authorityLinks(): HasMany
    {
        return $this->hasMany(IssueAuthorityLink::class);
    }

    // ── Scopes ─────────────────────────────────────────

    /**
     * How many districts have at least one active issue.
     * Issues can be pinned to any admin level, so each pinned area is
     * walked up the tree until its district ancestor is found.
     */
    public static function activeDistrictCount(): int
    {
        $ids = static::active()->whereNotNull('admin_area_id')->distinct()->pluck('admin_area_id');
        if ($ids->isEmpty()) {
            return 0;
        }

        $levels  = AdminArea::query()->pluck('level', 'id');
        $parents = AdminArea::query()->pluck('parent_id', 'id');

        $districts = [];
        foreach ($ids as $id) {
            $cur = (int) $id;
            for ($i = 0; $i < 8 && $cur > 0; $i++) {
                if (($levels[$cur] ?? null) === 'district') {
                    $districts[$cur] = true;
                    break;
                }
                $cur = (int) ($parents[$cur] ?? 0);
            }
        }

        return count($districts);
    }

    public function scopeActive($query)
    {
        return $query->whereIn('disposition', ['active', 'reopened'])->whereNotNull('published_at');
    }

    public function scopeNearby($query, float $lat, float $lng, int $meters = 100)
    {
        $degLat = $meters / 111000;
        $degLng = $meters / (111000 * max(cos(deg2rad($lat)), 0.01));

        return $query
            ->select('issues.*')
            ->selectRaw(
                '6371000 * ACOS(LEAST(1,
                    COS(RADIANS(?)) * COS(RADIANS(lat)) *
                    COS(RADIANS(lng) - RADIANS(?)) +
                    SIN(RADIANS(?)) * SIN(RADIANS(lat))
                )) AS meters',
                [$lat, $lng, $lat]
            )
            ->whereBetween('lat', [$lat - $degLat, $lat + $degLat])
            ->whereBetween('lng', [$lng - $degLng, $lng + $degLng])
            ->having('meters', '<=', $meters)
            ->orderBy('meters');
    }

    // ── Helpers ────────────────────────────────────────

    public function isOpen(): bool
    {
        return in_array($this->disposition, ['active', 'reopened'], true);
    }

    public function latestEvent(?string $dimension = null): ?StatusEvent
    {
        $q = $this->statusEvents();
        if ($dimension) {
            $q->where('dimension', $dimension);
        }
        return $q->first();
    }
}