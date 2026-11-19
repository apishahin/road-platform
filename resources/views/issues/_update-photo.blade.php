@php $pm = session('photo_msg'); @endphp
@if ($pm && ($pm['for'] ?? '') === ($for ?? ''))
    <p style="margin-top:8px;font-size:13px;color:{{ $pm['ok'] ? '#166534' : '#b91c1c' }}">{{ $pm['text'] }}</p>
@endif
@if (\App\Support\UpdatePhotos::enabled())
    <div x-data="{ fileName: '' }" style="margin-top:10px">
        <p class="text-sm font-medium text-slate-800">{{ __('issues.photo_title') }}</p>
        <p style="font-size:12px;color:#64748b">{{ __('issues.photo_note') }}</p>
        <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:6px">
            <label style="cursor:pointer;border:1px solid #94a3b8;border-radius:8px;padding:8px 12px;font-size:13px;font-weight:600;color:#0f172a;background:#fff">
                {{ __('issues.photo_camera') }}
                <input type="file" name="photo_camera" accept="image/*" capture="environment" data-compress style="display:none" x-on:change="fileName = $event.target.files.length ? $event.target.files[0].name : ''">
            </label>
            <label style="cursor:pointer;border:1px solid #94a3b8;border-radius:8px;padding:8px 12px;font-size:13px;font-weight:600;color:#0f172a;background:#fff">
                {{ __('issues.photo_gallery') }}
                <input type="file" name="photo_gallery" accept="image/jpeg,image/png,image/webp" data-compress style="display:none" x-on:change="fileName = $event.target.files.length ? $event.target.files[0].name : ''">
            </label>
        </div>
        <p x-show="fileName" x-text="fileName" style="margin-top:4px;font-size:12px;color:#475569"></p>
    </div>
@endif