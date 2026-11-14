@php
    $S  = \App\Support\SiteSections::class;
    $N  = fn ($v) => \App\Support\IssuePresenter::num($v);
    $ms = [];
if ($S::on('ribbon')) {
    try {
        if ($S::boolOn('ribbon', 'm1')) {
            $ms[] = ['n' => $N(\App\Models\Report::where('moderation', 'approved')->count()),
                     'l' => $S::txt('ribbon', 'l1', __('issues.ribbon_reports')),
                     'i' => '<path stroke-linecap="round" stroke-linejoin="round" d="M14.5 3.5a5 5 0 0 0-7.07 7.07L4 14l2.5 2.5M14.5 3.5 18 7m-3.5-3.5L20.5 9M18 7l2.5 2M8 15l5.5 5.5L19 15"/>',
                     'c' => '#0ea5e9', 'bg' => '#e0f2fe'];
        }
        if ($S::boolOn('ribbon', 'm2')) {
            $ms[] = ['n' => $N(\App\Models\Issue::active()->where('progress_status', 'repaired')->count()),
                     'l' => $S::txt('ribbon', 'l2', __('issues.ribbon_repaired')),
                     'i' => '<path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75"/><circle cx="12" cy="12" r="8.25"/>',
                     'c' => '#059669', 'bg' => '#d1fae5'];
        }
        if ($S::boolOn('ribbon', 'm3')) {
            $ms[] = ['n' => $N(\App\Models\User::count()),
                     'l' => $S::txt('ribbon', 'l3', __('issues.ribbon_users')),
                     'i' => '<path stroke-linecap="round" stroke-linejoin="round" d="M15 19a3 3 0 0 0-6 0m6 0h6m-6 0v-1.5M9 19H3m6 0v-1.5M12 11a3 3 0 1 0 0-6 3 3 0 0 0 0 6Zm7.5 4.5a2.25 2.25 0 0 0-4.5 0M4.5 15.5a2.25 2.25 0 0 1 4.5 0M18 9.75a2 2 0 1 0 0-4 2 2 0 0 0 0 4ZM8 9.75a2 2 0 1 1 0-4 2 2 0 0 1 0 4Z"/>',
                     'c' => '#7c3aed', 'bg' => '#ede9fe'];
        }
        if ($S::boolOn('ribbon', 'm4')) {
            $override = trim($S::txt('ribbon', 'district_n', ''));
            $count    = (int) \App\Models\AdminArea::where('level', 'district')->count();
            if ($override !== '' || $count > 0) {
                $ms[] = ['n' => $override !== '' ? $override : $N($count),
                         'l' => $S::txt('ribbon', 'l4', __('issues.ribbon_districts')),
                         'i' => '<path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.14-7.5 11.25-7.5 11.25S4.5 17.64 4.5 10.5a7.5 7.5 0 1 1 15 0Z"/>',
                         'c' => '#d97706', 'bg' => '#fef3c7'];
            }
        }
    } catch (\Throwable $e) {
        $ms = [];
    }
}
@endphp
@if (count($ms) > 0)
<section class="border-b border-slate-200 bg-slate-50 py-5 sm:py-7" data-stats-ribbon>
    <div class="mx-auto grid max-w-6xl grid-cols-2 gap-2.5 px-4 sm:gap-4 lg:grid-cols-4">
        @foreach ($ms as $m)
            <div class="flex items-center gap-3 rounded-2xl border border-slate-200 bg-white px-3.5 py-3.5 shadow-sm sm:px-5">
                <span class="grid size-11 shrink-0 place-items-center rounded-full" style="background:{{ $m['bg'] }};color:{{ $m['c'] }}">
                    <svg class="size-6" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24">{!! $m['i'] !!}</svg>
                </span>
                <div class="min-w-0">
                    <p class="truncate text-lg font-extrabold leading-tight text-slate-900 sm:text-2xl">{{ $m['n'] }}</p>
                    <p class="truncate text-[11px] font-medium text-slate-500 sm:text-xs">{{ $m['l'] }}</p>
                </div>
            </div>
        @endforeach
    </div>
</section>
@endif
