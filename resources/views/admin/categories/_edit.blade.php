<details class="text-right">
    <summary class="cursor-pointer rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-bold text-slate-700 hover:bg-slate-100">{{ __('core.edit') }}</summary>
    <form method="POST" action="{{ route('admin.categories.update', $category) }}" class="mt-2 grid w-[min(94vw,620px)] gap-2 rounded-xl border border-slate-200 bg-white p-3 text-left shadow-lg sm:grid-cols-2">
        @csrf @method('PUT')
        <label class="text-xs font-bold text-slate-600">{{ __('core.category_parent') }}
            <select name="parent_id" class="mt-1 block w-full rounded border border-slate-300 p-2 text-sm">
                <option value="">{{ __('core.category_new_main') }}</option>
                @foreach ($parents as $candidate)
                    @if ($candidate->id !== $category->id)
                        <option value="{{ $candidate->id }}" @selected($category->parent_id === $candidate->id)>{{ $candidate->name() }}</option>
                    @endif
                @endforeach
            </select>
        </label>
        <label class="text-xs font-bold text-slate-600">{{ __('core.category_order') }}
            <input type="number" name="sort" value="{{ $category->sort }}" min="0" max="65535" class="mt-1 block w-full rounded border border-slate-300 p-2 text-sm">
        </label>
        <label class="text-xs font-bold text-slate-600">{{ __('core.category_bn') }}
            <input required name="name_bn" value="{{ $category->name_bn }}" maxlength="100" class="mt-1 block w-full rounded border border-slate-300 p-2 text-sm">
        </label>
        <label class="text-xs font-bold text-slate-600">{{ __('core.category_en') }}
            <input name="name_en" value="{{ $category->name_en }}" maxlength="100" class="mt-1 block w-full rounded border border-slate-300 p-2 text-sm">
        </label>
        <label class="text-xs font-bold text-slate-600">{{ __('core.category_slug') }}
            <input required name="slug" value="{{ $category->slug }}" maxlength="50" pattern="[a-z0-9-]+" class="mt-1 block w-full rounded border border-slate-300 p-2 text-sm">
        </label>
        <div class="flex flex-wrap items-end gap-3 text-xs font-semibold text-slate-700">
            <label><input type="checkbox" name="is_active" value="1" @checked($category->is_active)> {{ __('core.category_active') }}</label>
            <label><input type="checkbox" name="is_reportable" value="1" @checked($category->is_reportable)> {{ __('core.category_reportable') }}</label>
            <button class="rounded bg-slate-800 px-3 py-2 font-bold text-white">{{ __('core.save') }}</button>
        </div>
    </form>
</details>
