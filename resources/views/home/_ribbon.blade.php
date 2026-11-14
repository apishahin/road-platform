{{-- Stats ribbon (reference port): green-soft strip, real DB counters,
     per-metric on/off + label/district overrides from admin. --}}
@php
    $S  = \App\Support\SiteSections::class;
    $N  = fn ($v) => \App\Support\IssuePresenter::num($v);
    $ms = [];
    if ($S::on('ribbon')) {
        try {
            if ($S::boolOn('ribbon', 'm1')) {
                $ms[] = ['n' => $N(\App\Models\Report::where('moderation', 'approved')->count()) . '+',
                         'l' => $S::txt('ribbon', 'l1', __('issues.ribbon_reports')),
                         'i' => '<path stroke-linecap="round" stroke-linejoin="round" d="M12 4 4.5 8.5 12 13l7.5-4.5L12 4Z"/><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12 7.5 4.5L19.5 12M4.5 15.5 12 20l7.5-4.5"/>'];
            }
            if ($S::boolOn('ribbon', 'm2')) {
                $ms[] = ['n' => $N(\App\Models\Issue::active()->where('progress_status', 'repaired')->count()) . '+',
                         'l' => $S::txt('ribbon', 'l2', __('issues.ribbon_repaired')),
                         'i' => '<path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>'];
            }
            if ($S::boolOn('ribbon', 'm3')) {
                $ms[] = ['n' => $N(\App\Models\User::count()) . '+',
                         'l' => $S::txt('ribbon', 'l3', __('issues.ribbon_users')),
                         'i' => '<path stroke-linecap="round" stroke-linejoin="round" d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path stroke-linecap="round" d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>'];
            }
            if ($S::boolOn('ribbon', 'm4')) {
                $override = trim($S::txt('ribbon', 'district_n', ''));
                $count    = (int) \App\Models\AdminArea::where('level', 'district')->count();
                if ($override !== '' || $count > 0) {
                    $ms[] = ['n' => $override !== '' ? $override : $N($count),
                             'l' => $S::txt('ribbon', 'l4', __('issues.ribbon_districts')),
                             'i' => '<path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.14-7.5 11.25-7.5 11.25S4.5 17.64 4.5 10.5a7.5 7.5 0 1 1 15 0Z"/>'];
                }
            }
        } catch (\Throwable $e) {
            $ms = [];
        }
    }
@endphp
@if (count($ms) > 0)
<div class="rp-wrap">
    <div class="stats-strip" data-stats-ribbon>
        @foreach ($ms as $m)
            <div class="stat-box">
                <span class="stat-icon" aria-hidden="true"><svg style="width:22px;height:22px;" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24">{!! $m['i'] !!}</svg></span>
                <strong>{{ $m['n'] }}</strong>
                <small>{{ $m['l'] }}</small>
            </div>
        @endforeach
    </div>
</div>
@endif
