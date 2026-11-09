@extends('layouts.app')

@section('title', __('authcontact.title'))

@section('content')
<div class="mx-auto max-w-4xl px-4 py-8">
    <h1 class="text-2xl font-bold text-slate-900">{{ __('authcontact.title') }}</h1>
    <p class="mt-1 text-xs text-slate-500">{{ __('authcontact.private_note') }}</p>

    @if (session('status'))
        <div class="mt-4 rounded-lg bg-green-50 p-3 text-sm text-green-800">{{ session('status') }}</div>
    @endif
    @if (session('import_errors'))
        <div class="mt-3 rounded-lg bg-amber-50 p-3 text-xs text-amber-900">
            @foreach (session('import_errors') as $e)
                <div>{{ $e }}</div>
            @endforeach
        </div>
    @endif
    @if ($errors->any())
        <div class="mt-3 rounded-lg bg-red-50 p-3 text-sm text-red-800">{{ $errors->first() }}</div>
    @endif

    <div class="mt-6 rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
        <h2 class="font-semibold text-slate-900">{{ __('authcontact.add_title') }}</h2>
        <form method="POST" action="{{ route('admin.contacts.store') }}" class="mt-3 space-y-3">
            @csrf
            <div>
                <label class="block text-sm font-medium text-slate-700">{{ __('authcontact.f_area') }}</label>
                <input type="text" id="area-q" autocomplete="off" placeholder="{{ __('authcontact.search_hint') }}" style="display:block;width:100%;margin-top:4px;border:1px solid #cbd5e1;border-radius:8px;padding:8px;">
                <input type="hidden" name="admin_area_id" id="area-id" value="{{ old('admin_area_id') }}">
                <div id="area-pick" style="margin-top:4px;border-radius:8px;overflow:hidden;"></div>
                <div id="area-chosen" style="margin-top:4px;font-size:13px;font-weight:600;color:#0f172a;"></div>
            </div>
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:12px;">
                <label class="block text-sm font-medium text-slate-700">{{ __('authcontact.f_name') }}
                    <input type="text" name="name" value="{{ old('name') }}" maxlength="150" required style="display:block;width:100%;margin-top:4px;border:1px solid #cbd5e1;border-radius:8px;padding:8px;">
                </label>
                <label class="block text-sm font-medium text-slate-700">{{ __('authcontact.f_position') }}
                    <input type="text" name="position" value="{{ old('position') }}" maxlength="150" style="display:block;width:100%;margin-top:4px;border:1px solid #cbd5e1;border-radius:8px;padding:8px;">
                </label>
                <label class="block text-sm font-medium text-slate-700">{{ __('authcontact.f_phone') }}
                    <input type="text" name="phone" value="{{ old('phone') }}" maxlength="30" style="display:block;width:100%;margin-top:4px;border:1px solid #cbd5e1;border-radius:8px;padding:8px;">
                </label>
                <label class="block text-sm font-medium text-slate-700">{{ __('authcontact.f_email') }}
                    <input type="email" name="email" value="{{ old('email') }}" maxlength="150" style="display:block;width:100%;margin-top:4px;border:1px solid #cbd5e1;border-radius:8px;padding:8px;">
                </label>
                <label class="block text-sm font-medium text-slate-700">{{ __('authcontact.f_whatsapp') }}
                    <input type="text" name="whatsapp" value="{{ old('whatsapp') }}" maxlength="30" style="display:block;width:100%;margin-top:4px;border:1px solid #cbd5e1;border-radius:8px;padding:8px;">
                </label>
            </div>
            <button type="submit" style="background:#0f172a;color:#fff;border:0;border-radius:8px;padding:8px 18px;cursor:pointer;">{{ __('authcontact.add_btn') }}</button>
        </form>
    </div>

    @if ($isAdmin)
        <div class="mt-4 rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <h2 class="font-semibold text-slate-900">{{ __('authcontact.import_title') }}</h2>
            <p class="mt-1 text-xs text-slate-500">{{ __('authcontact.import_hint') }}</p>
            <div class="mt-2 flex flex-wrap gap-4 text-sm">
                <a href="{{ route('admin.contacts.template') }}" style="text-decoration:underline;">{{ __('authcontact.template_link') }}</a>
                <a href="{{ route('admin.contacts.areascsv') }}" style="text-decoration:underline;">{{ __('authcontact.areas_link') }}</a>
            </div>
            <form method="POST" action="{{ route('admin.contacts.import') }}" enctype="multipart/form-data" class="mt-3 flex flex-wrap items-center gap-3">
                @csrf
                <input type="file" name="file" accept=".csv,text/csv" required style="font-size:13px;">
                <button type="submit" style="background:#15803d;color:#fff;border:0;border-radius:8px;padding:6px 16px;cursor:pointer;">{{ __('authcontact.import_btn') }}</button>
            </form>
        </div>
    @endif

    <div class="mt-6 flex flex-wrap gap-2 text-sm">
        @php($tabs = [null => __('authcontact.tab_all'), 'pending' => __('authcontact.status_pending') . ($pendingCount ? ' (' . $pendingCount . ')' : ''), 'approved' => __('authcontact.status_approved'), 'disabled' => __('authcontact.status_disabled')])
        @foreach ($tabs as $key => $label)
            <a href="{{ route('admin.contacts.index', $key ? ['status' => $key] : []) }}" style="padding:6px 14px;border-radius:999px;border:1px solid #cbd5e1;{{ $status === $key ? 'background:#0f172a;color:#fff;' : '' }}">{{ $label }}</a>
        @endforeach
    </div>

    <div class="mt-4 space-y-3">
        @forelse ($contacts as $c)
            <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <div class="font-semibold text-slate-900">{{ $c->name }}@if ($c->position) <span class="text-sm font-normal text-slate-500">&middot; {{ $c->position }}</span>@endif</div>
                    <span style="font-size:12px;padding:2px 10px;border-radius:999px;{{ $c->status === 'approved' ? 'background:#dcfce7;color:#166534;' : ($c->status === 'pending' ? 'background:#fef3c7;color:#92400e;' : 'background:#e2e8f0;color:#475569;') }}">{{ __('authcontact.status_' . $c->status) }}</span>
                </div>
                <div class="mt-1 text-xs text-slate-500">{{ \App\Models\AuthorityContact::pathOf($c->area) }}</div>
                <div class="text-xs text-slate-500">{{ __('authcontact.added_by') }}: {{ $c->addedBy?->display_name ?? '-' }} (#{{ $c->added_by }}) &middot; {{ $c->addedBy ? __('adminusers.role_' . $c->addedBy->role) : '' }}@if($c->addedBy?->phone) &middot; ***{{ substr($c->addedBy->phone, -4) }}@endif</div>
                <div class="mt-1 text-sm text-slate-700">
                    @if ($c->phone) {{ $c->phone }} @endif
                    @if ($c->email) &middot; {{ $c->email }} @endif
                    @if ($c->whatsapp) &middot; WhatsApp: {{ $c->whatsapp }} @endif
                </div>
                @if ($isAdmin)
                    <div class="mt-3 flex gap-2">
                        @if ($c->status === 'pending')
                            <form method="POST" action="{{ route('admin.contacts.approve', ['contact' => $c->id]) }}">@csrf
                                <button type="submit" style="background:#15803d;color:#fff;border:0;border-radius:6px;padding:5px 12px;font-size:13px;cursor:pointer;">{{ __('authcontact.approve') }}</button>
                            </form>
                        @endif
                        @if ($c->status === 'disabled')
                            <form method="POST" action="{{ route('admin.contacts.enable', ['contact' => $c->id]) }}">@csrf
                                <button type="submit" style="background:#15803d;color:#fff;border:0;border-radius:6px;padding:5px 12px;font-size:13px;cursor:pointer;">{{ __('authcontact.enable') }}</button>
                            </form>
                        @else
                            <form method="POST" action="{{ route('admin.contacts.disable', ['contact' => $c->id]) }}">@csrf
                                <button type="submit" style="background:#dc2626;color:#fff;border:0;border-radius:6px;padding:5px 12px;font-size:13px;cursor:pointer;">{{ __('authcontact.disable') }}</button>
                            </form>
                        @endif
                    </div>
                @endif
            </div>
        @empty
            <p class="text-sm text-slate-500">{{ __('authcontact.none') }}</p>
        @endforelse
    </div>

    <div class="mt-6">{{ $contacts->links() }}</div>
</div>
@endsection

@push('scripts')
<script>
(function () {
  var q = document.getElementById('area-q'), box = document.getElementById('area-pick');
  var hid = document.getElementById('area-id'), chosen = document.getElementById('area-chosen'), t = null;
  var url = @json(route('admin.contacts.areas'));
  q.addEventListener('input', function () {
    clearTimeout(t);
    var v = q.value.trim();
    if (v.length < 2) { box.innerHTML = ''; return; }
    t = setTimeout(function () {
      fetch(url + '?q=' + encodeURIComponent(v), { headers: { 'Accept': 'application/json' } })
        .then(function (r) { return r.json(); })
        .then(function (list) {
          box.innerHTML = '';
          list.forEach(function (a) {
            var b = document.createElement('button');
            b.type = 'button';
            b.textContent = a.label;
            b.style.cssText = 'display:block;width:100%;text-align:left;padding:6px 8px;border-bottom:1px solid #e2e8f0;background:#f8fafc;cursor:pointer;font-size:13px;';
            b.addEventListener('click', function () {
              hid.value = a.id; chosen.textContent = a.label; box.innerHTML = ''; q.value = '';
            });
            box.appendChild(b);
          });
        });
    }, 250);
  });
})();
</script>
@endpush