@extends('layouts.app')

@section('content')
<div class="mx-auto max-w-4xl px-4 py-8">
    <h1 class="mb-2 text-2xl font-bold">{{ __('moderators.title') }}</h1>
    <p class="mb-1 text-sm text-gray-600">{{ __('moderators.hint') }}</p>
    <p class="mb-4 text-xs text-gray-500">{{ __('moderators.phone_note') }}</p>

    @if (session('status'))
        <div class="mb-4 rounded border border-green-300 bg-green-50 px-3 py-2 text-sm text-green-900">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="mb-4 rounded border border-red-300 bg-red-50 px-3 py-2 text-sm text-red-900">
            @foreach ($errors->all() as $e)<div>{{ $e }}</div>@endforeach
        </div>
    @endif

    @php $levels = ['division', 'district', 'upazila', 'union']; @endphp

    <form method="POST" action="{{ route('admin.moderators.store') }}" class="mb-8 rounded border p-4"
          x-data="areaChain(@js($divisions), @js($childrenBase))">
        @csrf
        <h2 class="mb-3 font-semibold">{{ __('moderators.add') }}</h2>

        <label class="mb-1 block text-sm">{{ __('moderators.phone') }}</label>
        <input type="text" name="phone" inputmode="tel" value="{{ old('phone') }}" class="mb-3 w-full max-w-xs rounded border px-3 py-2 text-sm">

        <label class="mb-1 block text-sm">{{ __('moderators.area') }}</label>
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
        <p class="mb-3 text-xs text-gray-500">{{ __('moderators.area_hint') }}</p>

        <input type="hidden" name="area_id" :value="areaId">
        <button type="submit" :disabled="!areaId || busy" class="rounded bg-brand-600 px-4 py-2 text-sm text-white disabled:opacity-50">{{ __('moderators.give') }}</button>
    </form>

    <h2 class="mb-2 font-semibold">{{ __('moderators.active') }}</h2>
    @if ($grants->isEmpty())
        <p class="mb-8 text-sm text-gray-600">{{ __('moderators.none') }}</p>
    @else
        <div class="mb-8 overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead>
                    <tr class="border-b">
                        <th class="py-2 pr-3">{{ __('moderators.name') }}</th>
                        <th class="py-2 pr-3">{{ __('moderators.phone') }}</th>
                        <th class="py-2 pr-3">{{ __('moderators.area') }}</th>
                        <th class="py-2 pr-3">{{ __('moderators.since') }}</th>
                        <th class="py-2"></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($grants as $g)
                        <tr class="border-b align-top">
                            <td class="py-2 pr-3">{{ $g->user?->display_name ?: '-' }}</td>
                            <td class="py-2 pr-3 whitespace-nowrap">{{ $g->user?->phone }}</td>
                            <td class="py-2 pr-3">{{ $paths[$g->id] ?? '' }}</td>
                            <td class="py-2 pr-3 whitespace-nowrap">{{ $g->created_at?->format('Y-m-d') }}</td>
                            <td class="py-2 whitespace-nowrap">
                                <form method="POST" action="{{ route('admin.moderators.revoke', $g) }}" class="inline"
                                      onsubmit="return confirm('{{ e(__('moderators.confirm_revoke')) }}')">
                                    @csrf
                                    <button type="submit" class="text-red-700 underline">{{ __('moderators.revoke') }}</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    @if ($past->isNotEmpty())
        <h2 class="mb-2 font-semibold text-gray-700">{{ __('moderators.past') }}</h2>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm text-gray-600">
                <tbody>
                    @foreach ($past as $g)
                        <tr class="border-b align-top">
                            <td class="py-2 pr-3">{{ $g->user?->display_name ?: '-' }}</td>
                            <td class="py-2 pr-3 whitespace-nowrap">{{ $g->user?->phone }}</td>
                            <td class="py-2 pr-3">{{ $paths[$g->id] ?? '' }}</td>
                            <td class="py-2 pr-3 whitespace-nowrap">{{ __('moderators.revoked_at') }}: {{ $g->revoked_at?->format('Y-m-d') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
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