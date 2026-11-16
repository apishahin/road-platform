{{-- Logged-in user menu (desktop avatar dropdown). Require auth before including. --}}
@auth
@php
    $__u = auth()->user();
    $__name = trim((string) ($__u->display_name ?? ''));
    $__initial = mb_strtoupper(mb_substr($__name !== '' ? $__name : 'U', 0, 1));
@endphp
<div class="relative hidden md:block" x-data="{ o: false }" @click.outside="o = false" @keydown.escape.window="o = false">
    <button type="button" @click="o = !o" :aria-expanded="o" aria-label="{{ __('issues.nav_my_account') }}"
            class="flex size-9 items-center justify-center rounded-full bg-gradient-to-br from-brand-600 to-amber-500 text-sm font-extrabold text-white shadow-sm ring-2 ring-white transition-shadow hover:ring-brand-100">
        {{ $__initial }}
    </button>
    <div x-show="o" x-cloak
         x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0 -translate-y-1" x-transition:enter-end="opacity-100 translate-y-0"
         x-transition:leave="transition ease-in duration-100" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0 -translate-y-1"
         class="absolute right-0 z-[1100] mt-2 w-60 rounded-2xl border border-slate-200 bg-white p-2 shadow-xl shadow-slate-900/5 ring-1 ring-slate-900/5">
        <div class="mb-1 border-b border-slate-100 px-2.5 py-2">
            <p class="truncate text-sm font-bold text-slate-900">{{ $__name !== '' ? $__name : 'User #' . $__u->id }}</p>
            <p class="mt-0.5 inline-flex items-center rounded-md bg-slate-100 px-1.5 py-0.5 text-[11px] font-bold text-slate-500">{{ __('adminusers.role_' . $__u->role) }}</p>
        </div>
        <a href="{{ route('account.password') }}" class="group flex items-center gap-2.5 rounded-lg px-2.5 py-2 text-sm font-medium text-slate-700 transition-colors hover:bg-slate-100 hover:text-slate-900">
            <span class="grid size-7 shrink-0 place-items-center rounded-md bg-slate-100 text-slate-500 transition-colors group-hover:bg-white group-hover:text-brand-600">
                <svg class="size-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><circle cx="8" cy="15" r="4.2"/><path stroke-linecap="round" stroke-linejoin="round" d="m11 12 8-8M16 7l2.5 2.5M13.5 9.5 16 12"/></svg>
            </span>
            <span class="flex-1">{{ __('acctpw.title') }}</span>
        </a>
        <a href="{{ route('account.phone') }}" class="group flex items-center gap-2.5 rounded-lg px-2.5 py-2 text-sm font-medium text-slate-700 transition-colors hover:bg-slate-100 hover:text-slate-900">
            <span class="grid size-7 shrink-0 place-items-center rounded-md bg-slate-100 text-slate-500 transition-colors group-hover:bg-white group-hover:text-brand-600">
                <svg class="size-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><rect x="7" y="2.5" width="10" height="19" rx="2.2"/><path stroke-linecap="round" d="M10.5 18.5h3"/></svg>
            </span>
            <span class="flex-1">{{ __('acctphone.nav_phone') }}</span>
        </a>
        <a href="{{ route('account.delete') }}" class="group flex items-center gap-2.5 rounded-lg px-2.5 py-2 text-sm font-medium text-rose-600 transition-colors hover:bg-rose-50">
            <span class="grid size-7 shrink-0 place-items-center rounded-md bg-rose-50 text-rose-500 transition-colors group-hover:bg-white">
                <svg class="size-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 6.5h15M9.5 6V4.8A1.3 1.3 0 0 1 10.8 3.5h2.4a1.3 1.3 0 0 1 1.3 1.3V6m3.2.5-.7 13A1.6 1.6 0 0 1 15.4 21H8.6a1.6 1.6 0 0 1-1.6-1.5l-.7-13M10 10.5v6M14 10.5v6"/></svg>
            </span>
            <span class="flex-1">{{ __('acctphone.del_link') }}</span>
        </a>
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="group flex w-full items-center gap-2.5 rounded-lg px-2.5 py-2 text-left text-sm font-medium text-rose-600 transition-colors hover:bg-rose-50">
                <span class="grid size-7 shrink-0 place-items-center rounded-md bg-rose-50 text-rose-500 transition-colors group-hover:bg-white">
                    <svg class="size-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3.5v7M6.3 6.3a8 8 0 1 0 11.4 0"/></svg>
                </span>
                <span class="flex-1">{{ __('login.logout') }}</span>
            </button>
        </form>
    </div>
</div>
@endauth
