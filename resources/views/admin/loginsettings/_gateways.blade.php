<section id="gateways" class="mt-10 border-t border-slate-200 pt-8">
    <h2 class="text-xl font-bold text-slate-900">{{ __('smsgw.title') }}</h2>
    <p class="mt-1 text-sm text-slate-600">{{ __('smsgw.intro') }}</p>

    <div class="mt-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
        {{ __('smsgw.key_note') }}
    </div>

    @foreach (['gw_ok' => ['green', 'border-green-200 bg-green-50 text-green-800'], 'gw_fail' => ['red', 'border-red-200 bg-red-50 text-red-800']] as $slot => [, $classes])
        @if (session($slot))
            <p class="mt-4 rounded-lg border px-4 py-3 text-sm {{ $classes }}">{{ session($slot) }}</p>
        @endif
    @endforeach

    @if ($errors->any())
        <p class="mt-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ $errors->first() }}</p>
    @endif

    <div class="mt-8 flex items-center justify-between">
        <h2 class="text-lg font-semibold text-slate-900">{{ __('smsgw.list_title') }}</h2>
        <p class="text-xs text-slate-500">{{ __('smsgw.test_warn') }}</p>
    </div>

    <div class="mt-3 space-y-3">
        @forelse ($gateways as $g)
            <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                <div class="flex flex-wrap items-center gap-2">
                    <span class="font-semibold text-slate-900">{{ $g->name }}</span>
                    <span class="rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-medium text-slate-600">
                        {{ $g->channel === 'whatsapp' ? __('smsgw.ch_wa') : __('smsgw.ch_sms') }}
                    </span>
                    <span class="rounded-full bg-slate-100 px-2.5 py-0.5 text-xs text-slate-500">#{{ $g->priority }}</span>
                    <span class="rounded-full px-2.5 py-0.5 text-xs font-medium {{ $g->hasKey() ? 'bg-sky-100 text-sky-700' : 'bg-slate-100 text-slate-500' }}">
                        {{ $g->hasKey() ? __('smsgw.key_yes') : __('smsgw.key_no') }}
                    </span>
                    <span class="rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $g->enabled ? 'bg-green-100 text-green-700' : 'bg-slate-200 text-slate-600' }}">
                        {{ $g->enabled ? __('smsgw.on') : __('smsgw.off') }}
                    </span>
                </div>

                @if ($g->last_status)
                    <p class="mt-2 text-xs {{ $g->last_status === 'ok' ? 'text-green-700' : 'text-red-700' }}">
                        {{ __('smsgw.last') }}: {{ $g->last_status }} {{ $g->last_used_at?->format('Y-m-d H:i') }} — {{ $g->last_note }}
                    </p>
                @endif

                <div class="mt-3 flex flex-wrap items-center gap-2">
                    <a href="{{ route('admin.loginsettings.index', ['edit' => $g->id]) }}"
                       class="rounded-lg bg-slate-100 px-3 py-1.5 text-xs font-semibold text-slate-800 hover:bg-slate-200">{{ __('smsgw.edit') }}</a>

                    <form method="POST" action="{{ route('admin.smsgateways.toggle', $g->id) }}">
                        @csrf
                        <button type="submit" class="rounded-lg bg-slate-900 px-3 py-1.5 text-xs font-semibold text-white hover:bg-slate-700">
                            {{ $g->enabled ? __('smsgw.turn_off') : __('smsgw.turn_on') }}
                        </button>
                    </form>

                    <form method="POST" action="{{ route('admin.smsgateways.delete', $g->id) }}" onsubmit="return confirm(@js(__('smsgw.delete_confirm')))">
                        @csrf
                        <button type="submit" class="rounded-lg bg-red-50 px-3 py-1.5 text-xs font-semibold text-red-700 hover:bg-red-100">
                            {{ __('smsgw.delete') }}
                        </button>
                    </form>

                    <form method="POST" action="{{ route('admin.smsgateways.test', $g->id) }}" class="flex flex-1 min-w-[220px] items-center gap-2">
                        @csrf
                        <input type="text" name="phone" placeholder="{{ __('smsgw.test_phone') }}" required
                               class="w-full rounded-lg border border-slate-300 px-2.5 py-1.5 text-xs">
                        <button type="submit" class="rounded-lg bg-blue-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-blue-700 whitespace-nowrap">
                            {{ __('smsgw.test_btn') }}
                        </button>
                    </form>
                </div>
            </div>
        @empty
            <p class="rounded-xl border border-dashed border-slate-300 bg-slate-50 px-4 py-6 text-center text-sm text-slate-500">{{ __('smsgw.empty') }}</p>
        @endforelse
    </div>

    <div class="mt-10 rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
        <h2 class="text-lg font-semibold text-slate-900">{{ $editing ? __('smsgw.form_edit') : __('smsgw.form_add') }}</h2>
        @unless ($editing)
            <a href="{{ route('admin.loginsettings.index', ['preset' => 'bulksmsbd']) }}" class="mt-1 inline-block text-sm text-blue-600 underline">{{ __('smsgw.preset') }}</a>
        @endunless

        <form method="POST" action="{{ route('admin.smsgateways.save') }}" class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
            @csrf
            <input type="hidden" name="id" value="{{ old('id', $form['id']) }}">

            <div>
                <label class="text-sm font-medium text-slate-700">{{ __('smsgw.name') }}</label>
                <input type="text" name="name" maxlength="80" required value="{{ old('name', $form['name']) }}"
                       class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            </div>

            <div>
                <label class="text-sm font-medium text-slate-700">{{ __('smsgw.channel') }}</label>
                <select name="channel" class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm bg-white">
                    <option value="sms" @selected(old('channel', $form['channel']) === 'sms')>{{ __('smsgw.ch_sms') }}</option>
                    <option value="whatsapp" @selected(old('channel', $form['channel']) === 'whatsapp')>{{ __('smsgw.ch_wa') }}</option>
                </select>
            </div>

            <div>
                <label class="text-sm font-medium text-slate-700">{{ __('smsgw.method') }}</label>
                <select name="method" class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm bg-white">
                    <option value="GET" @selected(old('method', $form['method']) === 'GET')>GET</option>
                    <option value="POST" @selected(old('method', $form['method']) === 'POST')>POST</option>
                </select>
            </div>

            <div>
                <label class="text-sm font-medium text-slate-700">{{ __('smsgw.body_type') }}</label>
                <select name="body_type" class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm bg-white">
                    <option value="query" @selected(old('body_type', $form['body_type']) === 'query')>{{ __('smsgw.bt_query') }}</option>
                    <option value="json" @selected(old('body_type', $form['body_type']) === 'json')>{{ __('smsgw.bt_json') }}</option>
                    <option value="form" @selected(old('body_type', $form['body_type']) === 'form')>{{ __('smsgw.bt_form') }}</option>
                </select>
            </div>

            <div class="sm:col-span-2">
                <label class="text-sm font-medium text-slate-700">{{ __('smsgw.url') }}</label>
                <input type="text" name="url" maxlength="500" required value="{{ old('url', $form['url']) }}"
                       class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2 font-mono text-sm">
            </div>

            <div class="sm:col-span-2">
                <label class="text-sm font-medium text-slate-700">{{ __('smsgw.params') }}</label>
                <textarea name="params" rows="4" class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2 font-mono text-sm">{{ old('params', $form['params']) }}</textarea>
            </div>

            <div class="sm:col-span-2">
                <label class="text-sm font-medium text-slate-700">{{ __('smsgw.headers') }}</label>
                <textarea name="headers" rows="2" class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2 font-mono text-sm">{{ old('headers', $form['headers']) }}</textarea>
            </div>

            <div>
                <label class="text-sm font-medium text-slate-700">{{ __('smsgw.sender_id') }}</label>
                <input type="text" name="sender_id" maxlength="60" value="{{ old('sender_id', $form['sender_id']) }}"
                       class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            </div>

            <div>
                <label class="text-sm font-medium text-slate-700">{{ __('smsgw.marker') }}</label>
                <input type="text" name="success_marker" maxlength="100" value="{{ old('success_marker', $form['success_marker']) }}"
                       class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            </div>

            <div>
                <label class="text-sm font-medium text-slate-700">{{ __('smsgw.priority') }}</label>
                <input type="number" name="priority" min="1" max="999" required value="{{ old('priority', $form['priority']) }}"
                       class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            </div>

            <div>
                <label class="text-sm font-medium text-slate-700">{{ __('smsgw.api_key') }}</label>
                <input type="password" name="api_key" autocomplete="off" maxlength="500" value=""
                       class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                @if ($editing)
                    <p class="mt-1 text-xs text-slate-500">{{ __('smsgw.api_key_keep') }}</p>
                    <label class="mt-1 flex items-center gap-2 text-xs text-slate-600">
                        <input type="checkbox" name="clear_key" value="1"> {{ __('smsgw.clear_key') }}
                    </label>
                @endif
            </div>

            <div class="sm:col-span-2">
                <input type="hidden" name="enabled" value="0">
                <label class="flex items-center gap-2 text-sm font-semibold text-slate-800">
                    <input type="checkbox" name="enabled" value="1" @checked((bool) old('enabled', $form['enabled'])) class="h-4 w-4">
                    {{ __('smsgw.enabled') }}
                </label>
            </div>

            <div class="sm:col-span-2">
                <button type="submit" class="rounded-lg bg-slate-900 px-5 py-2.5 text-sm font-semibold text-white hover:bg-slate-700">
                    {{ __('smsgw.save') }}
                </button>
            </div>
        </form>
    </div>
</div>
</div>
</section>
