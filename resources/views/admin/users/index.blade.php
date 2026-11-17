@extends('layouts.app')

@section('title', __('adminusers.title'))

@section('content')
<div class="mx-auto max-w-5xl px-4 py-8">

    {{-- Header --}}
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold text-slate-900">{{ __('adminusers.title') }}</h1>
            <p class="mt-1 text-sm text-slate-500">{{ __('adminusers.note') }}</p>
        </div>
        <span class="rounded-full bg-slate-100 px-3.5 py-1.5 text-xs font-bold text-slate-600 ring-1 ring-slate-200">
            {{ __('adminusers.total', ['count' => $users->total()]) }}
        </span>
    </div>

    @if(session('status'))
        <div class="mt-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800">{{ session('status') }}</div>
    @endif

    {{-- Filters --}}
    <form method="GET" action="{{ route('admin.users.index') }}" class="mt-5 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
        <div class="grid gap-2.5 md:grid-cols-[1fr_170px_200px_auto]">
            <input type="text" name="q" value="{{ $q }}" placeholder="{{ __('adminusers.search') }}"
                   class="w-full rounded-lg border border-slate-300 px-3.5 py-2.5 text-sm focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-100">
            <select name="role" class="rounded-lg border border-slate-300 px-3 py-2.5 text-sm text-slate-700 focus:border-brand-500 focus:outline-none">
                <option value="">{{ __('adminusers.all_roles') }}</option>
                @foreach(['citizen','volunteer','moderator','admin'] as $r)
                    <option value="{{ $r }}" @selected($role === $r)>{{ __('adminusers.role_' . $r) }}</option>
                @endforeach
            </select>
            <select name="v" class="rounded-lg border border-slate-300 px-3 py-2.5 text-sm text-slate-700 focus:border-brand-500 focus:outline-none">
                <option value="">{{ __('adminusers.all_verified') }}</option>
                @foreach(['verified','unverified','none'] as $vv)
                    <option value="{{ $vv }}" @selected($v === $vv)>{{ __('adminusers.f_' . $vv) }}</option>
                @endforeach
            </select>
            <button type="submit" class="rounded-lg bg-slate-900 px-5 py-2.5 text-sm font-semibold text-white transition-colors hover:bg-slate-700">{{ __('adminusers.go') }}</button>
        </div>
        <div class="relative mt-3">
            <input type="hidden" name="area" id="areaId" value="{{ $areaId ?: '' }}">
            <input type="text" id="areaInput" autocomplete="off" value="{{ $areaLabel }}" placeholder="{{ __('adminusers.area_ph') }}"
                   class="w-full rounded-lg border border-slate-300 px-3.5 py-2.5 text-sm focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-100">
            <div id="areaBox" class="absolute inset-x-0 top-full z-20 overflow-hidden rounded-b-lg bg-white shadow-xl ring-1 ring-slate-200 empty:shadow-none empty:ring-0"></div>
            <p class="mt-1.5 text-xs text-slate-500">
                {{ __('adminusers.area_note') }}
                @if($areaId)
                    <a href="{{ route('admin.users.index', array_filter(['q' => $q, 'role' => $role, 'v' => $v])) }}" class="ml-2 font-semibold text-rose-600 hover:underline">{{ __('adminusers.area_clear') }}</a>
                @endif
            </p>
        </div>
    </form>

    {{-- User list --}}
    <div class="mt-5 space-y-2.5">
        @forelse($users as $u)
            @php
                $nm = trim((string) ($u->display_name ?? ''));
                $initial = mb_strtoupper(mb_substr($nm !== '' ? $nm : 'U', 0, 1));
            @endphp
            <div class="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-slate-200 bg-white px-4 py-3.5 shadow-sm transition-shadow hover:shadow-md">
                <div class="flex min-w-0 items-center gap-3.5">
                    <span class="grid size-10 shrink-0 place-items-center rounded-full bg-gradient-to-br from-brand-600 to-amber-500 text-sm font-extrabold text-white shadow-sm">{{ $initial }}</span>
                    <div class="min-w-0">
                        <div class="truncate text-sm font-bold text-slate-900">
                            {{ $nm !== '' ? $nm : '-' }} <span class="font-normal text-slate-400">#{{ $u->id }}</span>
                        </div>
                        <div class="mt-1 flex flex-wrap items-center gap-1.5 text-xs">
                            <span class="inline-flex items-center rounded-md bg-slate-100 px-1.5 py-0.5 font-bold text-slate-500">{{ __('adminusers.role_' . $u->role) }}</span>
                            @if($u->phone)
                                <button type="button" onclick="navigator.clipboard && navigator.clipboard.writeText(this.dataset.n)" data-n="{{ $u->phone }}" title="{{ __('adminusers.copy_phone') }}" class="inline-flex items-center rounded-md bg-slate-100 px-1.5 py-0.5 font-mono text-[11px] font-bold text-slate-600 hover:bg-slate-200">{{ $u->phone }}</button>
                                @if($u->phone_verified_at)
                                    <span class="inline-flex items-center rounded-md bg-emerald-50 px-1.5 py-0.5 font-bold text-emerald-700 ring-1 ring-inset ring-emerald-200">✓ {{ __('adminusers.verified') }}</span>
                                @else
                                    <span class="inline-flex items-center rounded-md bg-amber-50 px-1.5 py-0.5 font-bold text-amber-700 ring-1 ring-inset ring-amber-200">⚠ {{ __('adminusers.unverified') }}</span>
                                @endif
                            @else
                                <span class="inline-flex items-center rounded-md bg-slate-100 px-1.5 py-0.5 font-bold text-slate-400">{{ __('adminusers.no_phone') }}</span>
                            @endif
                            @if($u->is_banned)
                                <span class="inline-flex items-center rounded-md bg-rose-50 px-1.5 py-0.5 font-bold text-rose-700 ring-1 ring-inset ring-rose-200">{{ __('adminusers.banned') }}</span>
                            @endif
                        </div>
                        @foreach(($labels[$u->id] ?? []) as $lb)
                            <div class="mt-1 text-xs text-slate-500">{{ __('adminusers.work_area') }}: {{ $lb }}</div>
                        @endforeach
                    </div>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    @if($u->id !== auth()->id() && in_array($u->role, ['citizen','volunteer','moderator'], true))
                        <form method="POST" action="{{ route('admin.users.role', ['id' => $u->id]) }}" onsubmit="return confirm('{{ __('adminusers.confirm') }}')" class="flex gap-1.5">
                            @csrf
                            <select name="role" class="rounded-lg border border-slate-300 px-2 py-1.5 text-xs text-slate-700 focus:border-brand-500 focus:outline-none">
                                @foreach(['citizen','volunteer','moderator'] as $r)
                                    <option value="{{ $r }}" @selected($u->role === $r)>{{ __('adminusers.role_' . $r) }}</option>
                                @endforeach
                            </select>
                            <button type="submit" class="rounded-lg bg-slate-900 px-3 py-1.5 text-xs font-semibold text-white transition-colors hover:bg-slate-700">{{ __('adminusers.col_action') }}</button>
                        </form>
                        <form method="POST" action="{{ route('admin.users.block', ['id' => $u->id]) }}" onsubmit="return confirm('{{ __('adminusers.block_confirm') }}')" class="flex gap-1.5">
                            @csrf
                            @if($u->is_banned)
                                <button formaction="{{ route('admin.users.unblock', ['id' => $u->id]) }}" type="submit" class="rounded-lg bg-emerald-600 px-3 py-1.5 text-xs font-semibold text-white transition-colors hover:bg-emerald-700">{{ __('adminusers.unblock') }}</button>
                            @else
                                <button type="submit" class="rounded-lg bg-amber-600 px-3 py-1.5 text-xs font-semibold text-white transition-colors hover:bg-amber-700">{{ __('adminusers.block') }}</button>
                            @endif
                            <button formaction="{{ route('admin.users.delete', ['id' => $u->id]) }}" onclick="return confirm('{{ __('adminusers.delete_confirm') }}')" type="submit" class="rounded-lg bg-rose-600 px-3 py-1.5 text-xs font-semibold text-white transition-colors hover:bg-rose-700">{{ __('adminusers.delete') }}</button>
                        </form>
                    @else
                        <span class="text-xs text-slate-300">—</span>
                    @endif
                </div>
            </div>
        @empty
            <p class="rounded-2xl border border-dashed border-slate-300 py-10 text-center text-sm text-slate-400">{{ __('adminusers.none') }}</p>
        @endforelse
    </div>

    <div class="mt-6">{{ $users->links() }}</div>

    {{-- Blocked device/IP list --}}
    @if($blockedIps->isNotEmpty())
        <div class="mt-8 rounded-2xl border border-rose-200 bg-rose-50/40 p-4">
            <h2 class="text-base font-bold text-rose-800">{{ __('adminusers.blocked_title') }}</h2>
            <div class="mt-3 space-y-2">
                @foreach($blockedIps as $b)
                    <div class="flex flex-wrap items-center justify-between gap-2 rounded-xl border border-rose-100 bg-white px-3.5 py-2.5">
                        <div class="text-sm text-slate-700">
                            <code class="rounded bg-slate-100 px-1.5 py-0.5 text-xs font-bold text-slate-800">{{ $b->ip }}</code>
                            @if($b->user_id) <span class="text-slate-400">&middot; #{{ $b->user_id }}</span> @endif
                            <span class="ml-1 text-xs text-slate-400">{{ $b->created_at->diffForHumans() }}</span>
                        </div>
                        <form method="POST" action="{{ route('admin.users.unblockip', ['id' => $b->id]) }}">
                            @csrf
                            <button type="submit" class="rounded-lg bg-emerald-600 px-3 py-1.5 text-xs font-semibold text-white transition-colors hover:bg-emerald-700">{{ __('adminusers.ip_unblock') }}</button>
                        </form>
                    </div>
                @endforeach
            </div>
        </div>
    @endif
</div>

<script>
(function () {
    var inp = document.getElementById('areaInput');
    var hid = document.getElementById('areaId');
    var box = document.getElementById('areaBox');
    var url = @json(route('admin.users.areas'));
    var timer = null;
    inp.addEventListener('input', function () {
        hid.value = '';
        clearTimeout(timer);
        var v = inp.value.trim();
        box.innerHTML = '';
        if (v.length < 2) { return; }
        timer = setTimeout(function () {
            fetch(url + '?q=' + encodeURIComponent(v), { headers: { 'Accept': 'application/json' } })
                .then(function (r) { return r.json(); })
                .then(function (rows) {
                    box.innerHTML = '';
                    rows.forEach(function (row) {
                        var b = document.createElement('button');
                        b.type = 'button';
                        b.textContent = row.label;
                        b.className = 'block w-full cursor-pointer border-b border-slate-100 px-3.5 py-2.5 text-left text-sm text-slate-700 hover:bg-brand-50';
                        b.addEventListener('click', function () {
                            hid.value = row.id;
                            inp.value = row.label;
                            box.innerHTML = '';
                        });
                        box.appendChild(b);
                    });
                });
        }, 300);
    });
})();
</script>
@endsection
