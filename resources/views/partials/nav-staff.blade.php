@php
    $u = auth()->user();
    $groups = [];
    $pending = 0;
    if ($u) {
        $isAdmin = $u->role === 'admin';
        $review = [];
        if ($u->canModerate()) {
            $review[] = [route('admin.moderation.index'), __('issues.nav_moderation'), 0];
        }
        if ($u->isModerator()) {
            $review[] = [route('admin.issues.index'), __('issueadmin.menu'), 0];
            $review[] = [route('admin.contacts.index'), __('authcontact.menu'), 0];
        }
        if ($isAdmin) {
            $pending = \App\Models\VolunteerApplication::where('status', 'pending')->count();
            $review[] = [route('admin.applications.index'), __('issues.nav_applications'), $pending];
        }
        if ($review) {
            $groups[] = ['label' => __('issues.nav_review'), 'items' => $review];
        }
        if ($isAdmin) {
            $groups[] = ['label' => __('issues.nav_people'), 'items' => [
                [route('admin.users.index'), __('adminusers.menu'), 0],
                [route('admin.moderators.index'), __('issues.nav_volunteers'), 0],
            ]];
            $groups[] = ['label' => __('issues.nav_settings'), 'items' => [
                [route('admin.loginsettings.index'), __('loginsettings.menu'), 0],
                [route('admin.smsgateways.index'), __('issues.nav_sms'), 0],
                [route('admin.mail.index'), __('mailsettings.menu'), 0],
                [route('admin.appearance.index'), __('issues.nav_appearance'), 0],
                [route('admin.storage.index'), __('issues.nav_storage'), 0],
            ]];
        }
    }
    $pill = 'background:#ea580c;color:#fff;border-radius:9999px;padding:0 6px;font-size:11px;line-height:18px;margin-left:6px';
    $canApply = $u && in_array($u->role, ['citizen', 'volunteer'], true);
@endphp

@if (! $mobile)
    @if ($groups)
        <div class="relative hidden md:block" x-data="{ o: false }" @click.outside="o = false" @keydown.escape.window="o = false">
            <button type="button" @click="o = !o" :aria-expanded="o"
                    class="inline-flex items-center gap-1 rounded-lg border border-slate-300 px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-100">
                {{ __('issues.nav_admin') }}
                @if ($pending > 0)<span style="{{ $pill }}">{{ $pending }}</span>@endif
                <svg class="size-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="m6 9 6 6 6-6"/></svg>
            </button>
            <div x-show="o" style="display:none"
                 class="absolute right-0 z-[1100] mt-2 w-64 rounded-xl border border-slate-200 bg-white py-2 shadow-lg">
                @foreach ($groups as $g)
                    <p class="px-4 pb-1 pt-2 text-xs font-semibold uppercase tracking-wide text-slate-400">{{ $g['label'] }}</p>
                    @foreach ($g['items'] as [$url, $label, $badge])
                        <a href="{{ $url }}" class="flex items-center justify-between px-4 py-2 text-sm text-slate-700 hover:bg-slate-50">
                            <span>{{ $label }}</span>
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
    <a href="{{ url('/report') }}" class="hidden rounded-lg bg-brand-600 px-3 py-1.5 text-sm font-semibold text-white hover:bg-brand-700 sm:inline-block">{{ __('Report a problem') }}</a>
@else
    @foreach ($groups as $g)
        <div class="border-t border-slate-200 pt-2">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">{{ $g['label'] }}</p>
            @foreach ($g['items'] as [$url, $label, $badge])
                <a href="{{ $url }}" class="block py-1.5 text-slate-700">{{ $label }}@if ($badge > 0)<span style="{{ $pill }}">{{ $badge }}</span>@endif</a>
            @endforeach
        </div>
    @endforeach
    @if ($canApply)
        <a href="{{ route('volunteer.apply') }}">{{ __('volapp.menu_apply') }}</a>
    @endif
    <a href="{{ url('/report') }}" class="rounded-lg bg-brand-600 px-3 py-2 text-center font-semibold text-white">{{ __('Report a problem') }}</a>
@endif