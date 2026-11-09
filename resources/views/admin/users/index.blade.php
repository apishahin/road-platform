@extends('layouts.app')

@section('title', __('adminusers.title'))

@section('content')
<div class="mx-auto max-w-4xl px-4 py-8">
    <h1 class="text-2xl font-bold text-slate-900">{{ __('adminusers.title') }}</h1>
    <p class="mt-1 text-sm text-slate-500">{{ __('adminusers.note') }}</p>

    @if(session('status'))
        <div style="margin-top:12px;padding:8px 12px;border-radius:8px;background:#ecfdf5;border:1px solid #a7f3d0;font-size:14px;">{{ session('status') }}</div>
    @endif

    <form method="GET" action="{{ route('admin.users.index') }}" style="margin-top:16px;display:flex;gap:8px;">
        <input type="text" name="q" value="{{ $q }}" placeholder="{{ __('adminusers.search') }}" style="flex:1;padding:8px 12px;border:1px solid #cbd5e1;border-radius:8px;">
        <button type="submit" style="padding:8px 16px;border-radius:8px;background:#0f172a;color:#fff;border:0;">{{ __('adminusers.go') }}</button>
    </form>

    <div style="margin-top:16px;">
        @forelse($users as $u)
            <div style="padding:12px;border:1px solid #e2e8f0;border-radius:10px;margin-bottom:8px;display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap;align-items:center;">
                <div>
                    <div style="font-weight:600;">{{ $u->display_name ?: '-' }} <span style="color:#64748b;font-weight:400;">#{{ $u->id }}</span></div>
                    <div style="font-size:13px;color:#64748b;">
                        {{ __('adminusers.role_' . $u->role) }}
                        @if($u->phone) &middot; ***{{ substr($u->phone, -4) }} @endif
                        @if($u->is_banned) &middot; <span style="color:#b91c1c;">{{ __('adminusers.banned') }}</span> @endif
                    </div>
                </div>
                <div>
                    @if($u->id !== auth()->id() && in_array($u->role, ['citizen','volunteer','moderator'], true))
                        <form method="POST" action="{{ route('admin.users.role', ['id' => $u->id]) }}" onsubmit="return confirm('{{ __('adminusers.confirm') }}')" style="display:flex;gap:6px;">
                            @csrf
                            <select name="role" style="padding:6px 8px;border:1px solid #cbd5e1;border-radius:8px;">
                                @foreach(['citizen','volunteer','moderator'] as $r)
                                    <option value="{{ $r }}" @selected($u->role === $r)>{{ __('adminusers.role_' . $r) }}</option>
                                @endforeach
                            </select>
                            <button type="submit" style="padding:6px 12px;border-radius:8px;background:#0f172a;color:#fff;border:0;">{{ __('adminusers.col_action') }}</button>
                        </form>
                    @else
                        <span style="font-size:13px;color:#94a3b8;">-</span>
                    @endif
                </div>
            </div>
        @empty
            <p style="color:#64748b;">{{ __('adminusers.none') }}</p>
        @endforelse
    </div>

    <div class="mt-6">{{ $users->links() }}</div>
</div>
@endsection