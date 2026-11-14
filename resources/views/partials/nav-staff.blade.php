@php
    $u = auth()->user();
    $groups = [];
    $pending = 0;
    if ($u) {
        $isAdmin = $u->role === 'admin';
        $review = [];
        if ($u->canModerate()) {
            $review[] = [route('admin.moderation.index'), __('issues.nav_moderation'), 0, 'shield'];
            $review[] = [route('verify.queue'), __('issues.nav_verify'), 0, 'verify'];
            $review[] = [route('photoreview.index'), __('issues.nav_photos'), 0, 'photo'];
        }
        if ($u->isModerator()) {
            $review[] = [route('admin.issues.index'), __('issueadmin.menu'), 0, 'doc'];
            $review[] = [route('admin.contacts.index'), __('authcontact.menu'), 0, 'chat'];
        }
        if ($isAdmin) {
            $pending = \App\Models\VolunteerApplication::where('status', 'pending')->count();
            $review[] = [route('admin.applications.index'), __('issues.nav_applications'), $pending, 'clipboard'];
        }
        if ($review) {
            $groups[] = ['label' => __('issues.nav_review'), 'items' => $review];
        }
        if ($isAdmin) {
            $groups[] = ['label' => __('issues.nav_people'), 'items' => [
                [route('admin.users.index'), __('adminusers.menu'), 0, 'users'],
                [route('admin.moderators.index'), __('issues.nav_volunteers'), 0, 'volunteer'],
            ]];
            $groups[] = ['label' => __('issues.nav_settings'), 'items' => [
                [route('admin.loginsettings.index'), __('loginsettings.menu'), 0, 'key'],
                [route('admin.mail.index'), __('mailsettings.menu'), 0, 'mail'],
                [route('admin.storage.index'), __('issues.nav_storage'), 0, 'storage'],
                [route('admin.funding.index'), __('issues.nav_funding'), 0, 'fund'],
                [route('admin.site.index'), __('issues.nav_site'), 0, 'layout'],
            ]];
        }
    }

    $icons = [
        'shield'    => '<svg class="size-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3l7 2.5v5c0 4.6-3 8-7 10.5C8 18.5 5 15.1 5 10.5v-5L12 3Z"/><path stroke-linecap="round" stroke-linejoin="round" d="m9.5 12 1.8 1.8L15 10"/></svg>',
        'verify'    => '<svg class="size-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4"/><circle cx="12" cy="12" r="8.5"/></svg>',
        'photo'     => '<svg class="size-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><rect x="3.5" y="5" width="17" height="14" rx="2.5"/><circle cx="9" cy="10" r="1.8"/><path stroke-linecap="round" stroke-linejoin="round" d="m5 18 4.5-4.5 3 3 3.5-3.5L20.5 17"/></svg>',
        'doc'       => '<svg class="size-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M7 3.5h7L19 8v12.5H7V3.5Z"/><path stroke-linecap="round" d="M13.5 3.5V8H19M9.5 12h5M9.5 15.5h5"/></svg>',
        'chat'      => '<svg class="size-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6.5A2.5 2.5 0 0 1 6.5 4h11A2.5 2.5 0 0 1 20 6.5v8a2.5 2.5 0 0 1-2.5 2.5H11l-4.4 3.5v-3.5H6.5A2.5 2.5 0 0 1 4 14.5v-8Z"/></svg>',
        'clipboard' => '<svg class="size-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><rect x="5.5" y="4.5" width="13" height="16" rx="2"/><path stroke-linecap="round" d="M9 4.5a1.5 1.5 0 0 1 1.5-1.5h3A1.5 1.5 0 0 1 15 4.5M9 11h6M9 14.5h6"/></svg>',
        'users'     => '<svg class="size-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><circle cx="9.5" cy="8.5" r="3"/><path stroke-linecap="round" d="M4 19.5c.7-3.2 2.8-4.8 5.5-4.8s4.8 1.6 5.5 4.8M16 6a2.8 2.8 0 1 1-.9 5.5M17.5 14.9c1.8.6 3 2 3.4 3.6"/></svg>',
        'volunteer' => '<svg class="size-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><circle cx="12" cy="7.5" r="3.2"/><path stroke-linecap="round" stroke-linejoin="round" d="m10.8 12-1.2 8L12 18l2.4 2-1.2-8M8 20.5h8"/></svg>',
        'key'       => '<svg class="size-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><circle cx="8" cy="15" r="4.2"/><path stroke-linecap="round" stroke-linejoin="round" d="m11 12 8-8M16 7l2.5 2.5M13.5 9.5 16 12"/></svg>',
        'sms'       => '<svg class="size-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><rect x="7" y="3" width="10" height="18" rx="2.2"/><path stroke-linecap="round" d="M11 18.5h2"/></svg>',
        'mail'      => '<svg class="size-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><rect x="3.5" y="5.5" width="17" height="13" rx="2.2"/><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 7.5 7.5 5.5 7.5-5.5"/></svg>',
        'brush'     => '<svg class="size-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 16.5 15.5 6l2.5 2.5L7.5 19H5v-2.5ZM13.5 8l2.5 2.5"/></svg>',
        'storage'   => '<svg class="size-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><ellipse cx="12" cy="6.5" rx="7.5" ry="2.8"/><path d="M4.5 6.5v11c0 1.6 3.4 2.8 7.5 2.8s7.5-1.2 7.5-2.8v-11"/><path d="M4.5 12c0 1.6 3.4 2.8 7.5 2.8s7.5-1.2 7.5-2.8"/></svg>',
        'fund'      => '<svg class="size-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><rect x="3" y="7" width="18" height="11" rx="2"/><circle cx="12" cy="12.5" r="2.6"/><path stroke-linecap="round" d="M6.5 12.5h.01M17.5 12.5h.01"/></svg>',
        'layout'    => '<svg class="size-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><rect x="3.5" y="3.5" width="17" height="17" rx="2.5"/><path d="M3.5 9h17M9.5 9v11.5"/></svg>',
        'report'    => '<svg class="size-4" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M12 5v14M5 12h14"/></svg>',
    ];

    $pill = 'background:#ea580c;color:#fff;border-radius:9999px;padding:0 6px;font-size:11px;line-height:18px;font-weight:700;';
    $canApply = $u && in_array($u->role, ['citizen', 'volunteer'], true);
@endphp

@if (! $mobile)
    @if ($groups)
        <div class="relative hidden md:block" x-data="{ o: false }" @click.outside="o = false" @keydown.escape.window="o = false">
            <button type="button" @click="o = !o" :aria-expanded="o"
                    class="inline-flex items-center gap-1.5 rounded-xl border px-3 py-1.5 text-sm font-semibold transition-colors {{ url()->current() === url('/') ? '' : '' }} border-slate-300 text-slate-700 hover:border-slate-400 hover:bg-slate-50">
                {{ __('issues.nav_admin') }}
                @if ($pending > 0)<span style="{{ $pill }}margin-left:2px;">{{ $pending }}</span>@endif
                <svg class="size-4 transition-transform duration-200" :class="o && 'rotate-180'" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="m6 9 6 6 6-6"/></svg>
            </button>
            <div x-show="o" x-cloak
                 x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0 -translate-y-1" x-transition:enter-end="opacity-100 translate-y-0"
                 x-transition:leave="transition ease-in duration-100" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0 -translate-y-1"
                 style="max-height:75vh;overflow-y:auto;overscroll-behavior:contain;" class="absolute right-0 z-[1100] mt-2 w-72 rounded-2xl border border-slate-200 bg-white p-2 shadow-xl shadow-slate-900/5 ring-1 ring-slate-900/5">
                @foreach ($groups as $gi => $g)
                    @if ($gi > 0)<div class="my-1.5 border-t border-slate-100"></div>@endif
                    <p class="px-2.5 pb-1 pt-2 text-[11px] font-bold uppercase tracking-wider text-slate-400">{{ $g['label'] }}</p>
                    @foreach ($g['items'] as [$url, $label, $badge, $icon])
                        <a href="{{ $url }}" class="group flex items-center gap-2.5 rounded-lg px-2.5 py-2 text-sm font-medium text-slate-700 transition-colors hover:bg-slate-100 hover:text-slate-900">
                            <span class="grid size-7 shrink-0 place-items-center rounded-md bg-slate-100 text-slate-500 transition-colors group-hover:bg-white group-hover:text-brand-600">{!! $icons[$icon] !!}</span>
                            <span class="flex-1">{{ $label }}</span>
                            @if ($badge > 0)<span style="{{ $pill }}">{{ $badge }}</span>@endif
                        </a>
                    @endforeach
                @endforeach
            </div>
        </div>
    @endif
    @if ($canApply)
        <a href="{{ route('volunteer.apply') }}" class="hidden text-sm font-medium text-slate-700 hover:text-slate-900 sm:inline-block">{{ __('volapp.menu_apply') }}</a>
    @endif
    @if (\App\Support\SiteSections::navOn('fund'))
        <a href="{{ route('fund.show') }}" class="hidden text-sm font-medium text-emerald-700 hover:text-emerald-800 sm:inline-block">{{ __('issues.site_nav_fund') }}</a>
    @endif
    @if (\App\Support\SiteSections::navOn('report'))
    <a href="{{ url('/report') }}" class="rp-pill rp-report items-center gap-1.5">
        {!! $icons['report'] !!}{{ __('Report a problem') }}
    </a>
    @endif
@else
    @foreach ($groups as $g)
        <div class="rounded-xl border border-slate-200 bg-slate-50 p-2.5">
            <p class="px-1.5 pb-1.5 text-[11px] font-bold uppercase tracking-wider text-slate-400">{{ $g['label'] }}</p>
            <div class="flex flex-col gap-0.5">
                @foreach ($g['items'] as [$url, $label, $badge, $icon])
                    <a href="{{ $url }}" class="flex items-center gap-2.5 rounded-lg bg-white px-2.5 py-2 text-sm font-medium text-slate-700 shadow-sm">
                        <span class="grid size-7 shrink-0 place-items-center rounded-md bg-slate-100 text-slate-500">{!! $icons[$icon] !!}</span>
                        <span class="flex-1">{{ $label }}</span>
                        @if ($badge > 0)<span style="{{ $pill }}">{{ $badge }}</span>@endif
                    </a>
                @endforeach
            </div>
        </div>
    @endforeach
    @if ($canApply)
        <a href="{{ route('volunteer.apply') }}" class="text-sm font-medium text-slate-700">{{ __('volapp.menu_apply') }}</a>
    @endif
    @if (\App\Support\SiteSections::navOn('fund'))
        <a href="{{ route('fund.show') }}" class="flex items-center gap-2 rounded-lg bg-emerald-50 px-2.5 py-2 text-sm font-bold text-emerald-700">
            {!! $icons['fund'] !!} {{ __('issues.site_nav_fund') }}
        </a>
    @endif
    @if (\App\Support\SiteSections::navOn('report'))
    <a href="{{ url('/report') }}" class="flex items-center justify-center gap-1.5 rounded-xl bg-brand-600 px-3 py-2.5 text-sm font-bold text-white shadow-sm">
        {!! $icons['report'] !!}{{ __('Report a problem') }}
    </a>
    @endif
@endif
