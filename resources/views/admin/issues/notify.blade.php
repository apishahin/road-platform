@extends('layouts.app')

@section('title', __('notify.title'))

@section('content')
<div class="mx-auto max-w-3xl px-4 py-8">
    <h1 class="text-2xl font-bold text-slate-900">{{ __('notify.title') }}</h1>
    <p class="mt-1 text-sm text-slate-600">{{ __('notify.issue') }}: <strong>{{ $issue->title }}</strong>
        <a href="{{ route('issues.show', $issue->public_id) }}" target="_blank" rel="noopener" style="margin-left:8px;font-size:13px;text-decoration:underline;">#{{ $issue->id }}</a>
    </p>
    <p style="margin-top:4px;"><a href="{{ route('admin.issues.index') }}" style="font-size:13px;text-decoration:underline;">{{ __('notify.back') }}</a></p>

    @if($errors->any())
        <div style="margin-top:12px;padding:8px 12px;border-radius:8px;background:#fef2f2;border:1px solid #fecaca;font-size:14px;">{{ $errors->first() }}</div>
    @endif
    @if($mailMode === 'log')
        <div style="margin-top:12px;padding:8px 12px;border-radius:8px;background:#fffbeb;border:1px solid #fde68a;font-size:13px;">{{ __('notify.mail_log_note') }}</div>
    @endif

    @if(! $canSend)
        <div style="margin-top:16px;padding:10px 12px;border-radius:8px;background:#fef2f2;border:1px solid #fecaca;">{{ __('notify.not_published') }}</div>
    @elseif(! $issue->admin_area_id)
        <div style="margin-top:16px;padding:10px 12px;border-radius:8px;background:#fef2f2;border:1px solid #fecaca;">{{ __('notify.no_area') }}</div>
    @elseif($contacts->isEmpty())
        <div style="margin-top:16px;padding:10px 12px;border-radius:8px;background:#fffbeb;border:1px solid #fde68a;">
            {{ __('notify.no_contacts') }}
            <a href="{{ route('admin.contacts.index') }}" style="text-decoration:underline;margin-left:6px;">{{ __('authcontact.menu') }}</a>
        </div>
    @else
        <form method="POST" action="{{ route('admin.issues.notify.send', ['issue' => $issue->id]) }}" style="margin-top:16px;">
            @csrf
            <h2 style="font-weight:600;">{{ __('notify.contacts') }}</h2>
            <p style="font-size:13px;color:#64748b;">{{ __('notify.pick') }}</p>

            @foreach($contacts as $c)
                <div style="display:flex;justify-content:space-between;gap:12px;align-items:center;padding:10px;border:1px solid #e2e8f0;border-radius:10px;margin-top:8px;">
                    <label style="display:flex;gap:10px;align-items:flex-start;cursor:pointer;">
                        <input type="checkbox" name="contacts[]" value="{{ $c->id }}" @disabled(! $c->email) @checked(in_array($c->id, (array) old('contacts', []))) style="margin-top:4px;">
                        <span>
                            <span style="font-weight:600;">{{ $c->name }}</span>
                            @if($c->position) <span style="color:#64748b;">&middot; {{ $c->position }}</span> @endif
                            <br><span style="font-size:12px;color:#64748b;">{{ \App\Models\AuthorityContact::pathOf($c->area) }}</span>
                            <br><span style="font-size:12px;color:#64748b;">{{ $c->email ?: __('notify.no_email') }}</span>
                        </span>
                    </label>
                    <div>
                        @if(! empty($waNumbers[$c->id]))
                            <button type="submit" formaction="{{ route('admin.issues.notify.whatsapp', ['issue' => $issue->id, 'contact' => $c->id]) }}" formtarget="_blank" style="padding:6px 12px;border-radius:8px;background:#16a34a;color:#fff;border:0;font-size:13px;">{{ __('notify.whatsapp') }}</button>
                        @else
                            <span style="font-size:12px;color:#94a3b8;">{{ __('notify.no_whatsapp') }}</span>
                        @endif
                    </div>
                </div>
            @endforeach

            <label style="display:block;margin-top:16px;font-weight:600;">{{ __('notify.message') }}</label>
            <textarea name="message" rows="11" style="width:100%;padding:8px 12px;border:1px solid #cbd5e1;border-radius:8px;font-size:14px;">{{ $message }}</textarea>
            <p style="font-size:12px;color:#64748b;margin-top:4px;">{{ __('notify.message_help') }}</p>
            <p style="font-size:12px;color:#64748b;">{{ __('notify.whatsapp_help') }}</p>

            <button type="submit" onclick="return confirm('{{ __('notify.confirm_email') }}')" style="margin-top:12px;padding:8px 16px;border-radius:8px;background:#0f172a;color:#fff;border:0;">{{ __('notify.send_email') }}</button>
        </form>
    @endif

    <h2 style="margin-top:28px;font-weight:600;">{{ __('notify.history') }}</h2>
    @forelse($history as $h)
        <div style="font-size:13px;color:#475569;padding:6px 0;border-bottom:1px solid #f1f5f9;">
            {{ $h->created_at->format('Y-m-d H:i') }} &middot; {{ __('notify.ch_' . $h->channel) }} &middot; {{ $h->contact?->name ?? '-' }} &middot; {{ __('notify.st_' . $h->status) }} &middot; {{ __('notify.by') }} #{{ $h->sent_by }}
            @if($h->error) <span style="color:#b91c1c;">&middot; {{ $h->error }}</span> @endif
        </div>
    @empty
        <p style="font-size:13px;color:#64748b;">{{ __('notify.history_none') }}</p>
    @endforelse
</div>
@endsection
