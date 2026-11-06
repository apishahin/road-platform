@extends('layouts.app')

@section('content')
<div class="mx-auto max-w-2xl px-4 py-8">
    <h1 class="mb-2 text-2xl font-bold">{{ __('volapp.title') }}</h1>
    <p class="mb-1 text-sm text-gray-600">{{ __('volapp.hint') }}</p>
    <p class="mb-4 text-xs text-gray-500">{{ __('volapp.phone_note') }}</p>

    @if (session('status'))
        <div class="mb-4 rounded border border-green-300 bg-green-50 px-3 py-2 text-sm text-green-900">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="mb-4 rounded border border-red-300 bg-red-50 px-3 py-2 text-sm text-red-900">
            @foreach ($errors->all() as $e)<div>{{ $e }}</div>@endforeach
        </div>
    @endif

    @if (! $canApply)
        <p class="mb-6 rounded border border-amber-300 bg-amber-50 px-3 py-2 text-sm text-amber-900">{{ __('volapp.cant') }}</p>
    @elseif ($pending)
        <div class="mb-6 rounded border border-amber-300 bg-amber-50 px-3 py-3 text-sm text-amber-900">
            <div class="font-semibold">{{ __('volapp.pending_title') }}</div>
            <div class="mt-1">{{ $paths[$pending->id] ?? '' }}</div>
            <div class="mt-1">{{ __('volapp.pending_text') }}</div>
        </div>
    @else
        @php $levels = ['division', 'district', 'upazila', 'union']; @endphp
        <form method="POST" action="{{ route('volunteer.apply.store') }}" class="mb-8 rounded border p-4"
              x-data="areaChain(@js($divisions), @js($childrenBase))">
            @csrf

            <label class="mb-1 block text-sm font-medium">{{ __('volapp.area_q') }}</label>
            <div class="mb-1 flex flex-wrap gap-2">
                @foreach ($levels as $i => $lv)
                    <select x-model="sel[{{ $i }}]" @change="pick({{ $i }})" @if ($i > 0) x-show="opts[{{ $i }}].length" @endif class="rounded border px-2 py-2 text-sm">
                        <option value="">{{ __('areas.' . $lv) }}</option>
                        <template x-for="o in opts[{{ $i }}]" :key="o.id">
                            <option :value="o.id" x-text="o.label"></option>
                        </template>
                    </select>
                @endforeach
            </div>
            <p class="mb-4 text-xs text-gray-500">{{ __('volapp.area_hint') }}</p>
            <input type="hidden" name="area_id" :value="areaId">

            <label class="mb-1 block text-sm font-medium">{{ __('volapp.message') }}</label>
            <textarea name="message" rows="4" maxlength="1000" class="mb-4 w-full rounded border px-3 py-2 text-sm">{{ old('message') }}</textarea>

            <div class="mb-3 rounded bg-gray-50 p-3 text-sm">
                <div class="mb-1 font-medium">{{ __('volapp.rules_title') }}</div>
                <ol class="list-decimal space-y-1 pl-5 text-gray-700">
                    <li>{{ __('volapp.rule_1') }}</li>
                    <li>{{ __('volapp.rule_2') }}</li>
                    <li>{{ __('volapp.rule_3') }}</li>
                    <li>{{ __('volapp.rule_4') }}</li>
                    <li>{{ __('volapp.rule_5') }}</li>
                </ol>
            </div>
            <label class="mb-4 flex items-start gap-2 text-sm">
                <input type="checkbox" name="agree" value="1" class="mt-1">
                <span>{{ __('volapp.agree') }}</span>
            </label>

            <button type="submit" :disabled="!areaId || busy" class="rounded bg-brand-600 px-4 py-2 text-sm text-white disabled:opacity-50">{{ __('volapp.submit') }}</button>
        </form>
    @endif

    @if ($mine->isNotEmpty())
        <h2 class="mb-2 font-semibold">{{ __('volapp.mine') }}</h2>
        <div class="space-y-2">
            @foreach ($mine as $m)
                <div class="rounded border p-3 text-sm">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <span>{{ $paths[$m->id] ?? '' }}</span>
                        <span class="font-medium">{{ __('volapp.status_' . $m->status) }}</span>
                    </div>
                    <div class="text-xs text-gray-500">{{ $m->created_at?->format('Y-m-d') }}</div>
                    @if ($m->status === 'rejected' && $m->decision_note)
                        <div class="mt-1 text-gray-700">{{ __('volapp.reason') }}: {{ $m->decision_note }}</div>
                    @endif
                </div>
            @endforeach
        </div>
    @endif
</div>
@endsection

@push('scripts')
<script>
function areaChain(divisions, base) {
    return {
        sel: ['', '', '', ''],
        opts: [divisions, [], [], []],
        busy: false,
        get areaId() {
            for (let i = 3; i >= 0; i--) { if (this.sel[i]) return this.sel[i]; }
            return '';
        },
        async pick(i) {
            for (let j = i + 1; j < 4; j++) { this.sel[j] = ''; this.opts[j] = []; }
            if (!this.sel[i] || i >= 3) return;
            this.busy = true;
            try {
                const r = await fetch(base + '/' + this.sel[i] + '/children', {
                    headers: { 'Accept': 'application/json' },
                    credentials: 'same-origin'
                });
                this.opts[i + 1] = r.ok ? await r.json() : [];
            } catch (e) {
                this.opts[i + 1] = [];
            }
            this.busy = false;
        }
    };
}
</script>
@endpush