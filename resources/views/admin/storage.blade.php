@extends('layouts.app')

@section('content')
<div class="mx-auto max-w-3xl px-4 py-8">
    <h1 class="text-2xl font-bold">ছবির স্টোরেজ</h1>
    <p class="mt-1 text-sm text-slate-600">ছবি কোথায় জমা হবে তা এখান থেকে বাছুন। Cloudflare R2-এর endpoint, bucket ও access key-ও এখান থেকে সেট করা যায়; গোপন key database-এ APP_KEY দিয়ে এনক্রিপ্ট করে রাখা হয়।</p>

    @if (session('status'))
        <div class="mt-4 rounded-lg border border-green-200 bg-green-50 p-3 text-sm text-green-800">{{ session('status') }}</div>
    @endif

    @if ($errors->any())
        <div class="mt-4 rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-800">
            @foreach ($errors->all() as $error)<div>{{ $error }}</div>@endforeach
        </div>
    @endif

    @if (session('checks'))
        <div class="mt-4 rounded-lg border border-slate-200 bg-white p-3 text-sm">
            <div class="mb-1 font-semibold">পরীক্ষার ফল</div>
            @foreach (session('checks') as $c)
                <div class="{{ $c['result']['ok'] ? 'text-green-700' : 'text-red-700' }}">
                    {{ $c['result']['ok'] ? '✓' : '✗' }} {{ $c['label'] }}: {{ $c['result']['message'] }}
                </div>
            @endforeach
        </div>
    @endif

    <div class="mt-6 rounded-xl border border-slate-200 bg-white p-5">
        <div class="text-sm text-slate-500">এখন ব্যবহৃত</div>
        <div class="text-lg font-semibold">{{ $choice === 'r2' ? 'Cloudflare R2' : 'লোকাল ফোল্ডার' }}</div>
        <div class="mt-1 text-xs text-slate-500">প্রাইভেট ডিস্ক: {{ $private }} · পাবলিক ডিস্ক: {{ $public }}</div>
    </div>

    @foreach (['local' => 'লোকাল ফোল্ডার', 'r2' => 'Cloudflare R2'] as $key => $label)
        <div class="mt-4 rounded-xl border {{ $choice === $key ? 'border-brand-600' : 'border-slate-200' }} bg-white p-5">
            <div class="flex items-center justify-between">
                <div class="font-semibold">{{ $label }}</div>
                @if ($choice === $key)
                    <span class="rounded-full bg-green-100 px-2 py-0.5 text-xs font-semibold text-green-800">চালু আছে</span>
                @endif
            </div>
            <p class="mt-1 text-sm text-slate-600">
                @if ($key === 'local')
                    ছবি এই সার্ভারের storage ফোল্ডারে থাকে। পরীক্ষার জন্য ভালো।
                @elseif ($r2['stored_in_database'])
                    R2-এর তথ্য Admin settings-এ এনক্রিপ্ট করে সংরক্ষিত আছে।
                @elseif ($r2Ready)
                    পুরনো .env-এ R2-এর তথ্য আছে। নিচের form একবার save করলে সেগুলো নিরাপদে Admin settings-এ চলে আসবে; এরপর .env edit করার দরকার হবে না।
                @else
                    নিচের form-এ R2-এর সব তথ্য দিন, save করুন, তারপর সংযোগ পরীক্ষা করুন।
                @endif
            </p>

            @if ($key === 'r2')
                <form method="POST" action="{{ route('admin.storage.r2') }}" autocomplete="off" class="mt-4 rounded-lg border border-sky-100 bg-sky-50 p-4">
                    @csrf
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <div class="font-semibold text-slate-900">Cloudflare R2 সংযোগ সেটআপ</div>
                        <span class="text-xs text-slate-600">Secret key কখনো এখানে দেখানো হবে না।</span>
                    </div>
                    <p class="mt-1 text-xs leading-5 text-slate-600">Cloudflare Dashboard → R2 → Manage R2 API Tokens থেকে Access Key ID, Secret Access Key এবং account endpoint নিন। Private bucket-এ মূল/অপেক্ষমাণ ছবি, public bucket-এ অনুমোদিত ছোট কপি রাখা হবে।</p>

                    <div class="mt-3 grid gap-3 sm:grid-cols-2">
                        <div class="sm:col-span-2">
                            <label for="r2_endpoint" class="text-xs font-semibold text-slate-700">S3 API endpoint</label>
                            <input id="r2_endpoint" name="r2_endpoint" type="url" required placeholder="https://ACCOUNT_ID.r2.cloudflarestorage.com"
                                   value="{{ old('r2_endpoint', $r2['endpoint']) }}" class="mt-1 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm">
                        </div>
                        <div>
                            <label for="r2_private_bucket" class="text-xs font-semibold text-slate-700">Private bucket</label>
                            <input id="r2_private_bucket" name="r2_private_bucket" type="text" required maxlength="63" autocapitalize="none"
                                   value="{{ old('r2_private_bucket', $r2['private_bucket']) }}" class="mt-1 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm">
                            <p class="mt-1 text-xs text-slate-500">Original ও pending ছবি; public URL থাকবে না।</p>
                        </div>
                        <div>
                            <label for="r2_public_bucket" class="text-xs font-semibold text-slate-700">Public bucket</label>
                            <input id="r2_public_bucket" name="r2_public_bucket" type="text" required maxlength="63" autocapitalize="none"
                                   value="{{ old('r2_public_bucket', $r2['public_bucket']) }}" class="mt-1 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm">
                            <p class="mt-1 text-xs text-slate-500">শুধু অনুমোদিত, processed ছবি।</p>
                        </div>
                        <div class="sm:col-span-2">
                            <label for="r2_public_url" class="text-xs font-semibold text-slate-700">Public bucket URL বা custom domain</label>
                            <input id="r2_public_url" name="r2_public_url" type="url" required placeholder="https://images.example.com"
                                   value="{{ old('r2_public_url', $r2['public_url']) }}" class="mt-1 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm">
                        </div>
                        <div>
                            <label for="r2_access_key_id" class="text-xs font-semibold text-slate-700">R2 Access Key ID</label>
                            <input id="r2_access_key_id" name="r2_access_key_id" type="password" maxlength="300" autocomplete="new-password"
                                   placeholder="{{ $r2['has_access_key_id'] ? 'সংরক্ষিত আছে — বদলাতে চাইলে দিন' : 'Access Key ID দিন' }}"
                                   class="mt-1 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm">
                            @if ($r2['has_access_key_id'])
                                <label class="mt-1.5 flex items-center gap-1.5 text-xs text-rose-700"><input type="checkbox" name="clear_r2_access_key_id" value="1"> Access Key ID মুছে দিন</label>
                            @endif
                        </div>
                        <div>
                            <label for="r2_secret_access_key" class="text-xs font-semibold text-slate-700">R2 Secret Access Key</label>
                            <input id="r2_secret_access_key" name="r2_secret_access_key" type="password" maxlength="300" autocomplete="new-password"
                                   placeholder="{{ $r2['has_secret_access_key'] ? 'সংরক্ষিত আছে — বদলাতে চাইলে দিন' : 'Secret Access Key দিন' }}"
                                   class="mt-1 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm">
                            @if ($r2['has_secret_access_key'])
                                <label class="mt-1.5 flex items-center gap-1.5 text-xs text-rose-700"><input type="checkbox" name="clear_r2_secret_access_key" value="1"> Secret Access Key মুছে দিন</label>
                            @endif
                        </div>
                    </div>
                    <button type="submit" class="mt-4 rounded-lg bg-sky-700 px-3 py-2 text-sm font-semibold text-white hover:bg-sky-800">R2 সেটিং সংরক্ষণ করুন</button>
                </form>
            @endif

            <div class="mt-3 flex flex-wrap gap-2">
                <form method="POST" action="{{ route('admin.storage.check') }}">
                    @csrf
                    <input type="hidden" name="target" value="{{ $key }}">
                    <button class="rounded-lg border border-slate-300 px-3 py-1.5 text-sm font-medium hover:bg-slate-100">পরীক্ষা করুন</button>
                </form>
                @if ($choice !== $key)
                    <form method="POST" action="{{ route('admin.storage.update') }}">
                        @csrf
                        <input type="hidden" name="storage" value="{{ $key }}">
                        <button class="rounded-lg bg-brand-600 px-3 py-1.5 text-sm font-semibold text-white hover:bg-brand-700">এটি ব্যবহার করুন</button>
                    </form>
                @endif
            </div>
        </div>
    @endforeach

    <div class="mt-6 rounded-xl border border-slate-200 bg-white p-5">
        <div class="font-semibold">কোন ডিস্কে কয়টি ছবি (প্রাইভেট)</div>
        @forelse ($counts as $disk => $n)
            <div class="mt-1 text-sm">{{ $disk ?: '—' }}: {{ $n }} টি</div>
        @empty
            <div class="mt-1 text-sm text-slate-500">এখনো কোনো ছবি নেই।</div>
        @endforelse
    </div>

    @php $__upOn = \App\Support\UpdatePhotos::enabled(); @endphp
    <div class="mt-6 rounded-xl border border-slate-200 bg-white p-5" data-encode-settings>
        <div class="font-semibold">আপলোড ফটোর সাইজ ও ফরম্যাট</div>
        <p class="mt-1 text-sm text-slate-600">
            নতুন আপলোড স্বয়ংক্রিয়ভাবে এই নিয়মে ছোট ও কমপ্রেস হয়। WebP ফরম্যাট JPEG-এর চেয়ে গড়ে ২৫–৩৫% ছোট ফাইল দেয়।
            কোয়ালিটি কমালে ফাইল ছোট হয়, ছবি কিছুটা নরমাল দেখায় (৭৫–৮০ ভারসাম্যপূর্ণ)।
        </p>
        <form method="POST" action="{{ route('admin.storage.encode') }}" class="mt-3 grid gap-3 sm:grid-cols-3">
            @csrf
            <div>
                <label class="text-xs font-medium text-slate-600">সর্বোচ্চ সাইজ (লম্বা দিক)</label>
                <select name="max" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    @foreach (\App\Support\PhotoEncodeSettings::SIZES as $px)
                        <option value="{{ $px }}" @selected($encode['max'] === $px)>{{ $px }} px</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="text-xs font-medium text-slate-600">ফরম্যাট</label>
                <select name="format" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <option value="jpeg" @selected($encode['format'] === 'jpeg')>JPEG (সব ব্রাউজারে নিরাপদ)</option>
                    <option value="webp" @selected($encode['format'] === 'webp')>WebP (সবচেয়ে ছোট ফাইল)</option>
                </select>
            </div>
            <div>
                <label class="text-xs font-medium text-slate-600">কোয়ালিটি (৫০–৯২)</label>
                <input type="number" name="quality" value="{{ $encode['quality'] }}" min="50" max="92" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            </div>
            <div class="sm:col-span-3">
                <button type="submit" style="background:#16a34a;color:#fff;font-weight:600;border:0;border-radius:8px;padding:8px 16px">সংরক্ষণ করুন</button>
            </div>
        </form>
        @if ($encode['format'] === 'webp' && ! function_exists('imagewebp'))
            <p class="mt-2 text-sm text-rose-600">⚠️ এই সার্ভারে WebP সাপোর্ট (GD webp) নেই — JPEG রাখুন।</p>
        @endif
    </div>

    <div class="mt-6 rounded-xl border border-slate-200 bg-white p-5" data-keep-originals>
        <div class="font-semibold">অরিজিনাল ছবি সংরক্ষণ</div>
        <p class="mt-1 text-sm text-slate-600">
            চালু থাকলে আপলোডকৃত ছবির মূল কপিও রাখা হয় (বড় ফাইল)। বন্ধ করলে শুধু স্বয়ংক্রিয়ভাবে ছোট করা কপি (সর্বোচ্চ 1600px) থাকবে —
            স্টোরেজ প্রায় অর্ধেকে নেমে যাবে। মূল কপি মুখ ঝাপসা করার আগে পর্যন্ত দরকার হতে পারে; ঝাপসা করলে মূল কপি নিজে থেকেই মুছে যায়।
        </p>
        <p class="mt-2 text-sm">এখন: <strong style="color:{{ $keepOrig ? '#166534' : '#b91c1c' }}">{{ $keepOrig ? 'চালু (মূল কপি রাখা হচ্ছে)' : 'বন্ধ (শুধু ছোট কপি)' }}</strong></p>
        <form method="POST" action="{{ route('admin.storage.keep-originals') }}" class="mt-3">
            @csrf
            <input type="hidden" name="keep" value="{{ $keepOrig ? 0 : 1 }}">
            <button type="submit" style="background:{{ $keepOrig ? '#b91c1c' : '#16a34a' }};color:#fff;font-weight:600;border:0;border-radius:8px;padding:8px 16px">{{ $keepOrig ? 'অরিজিনাল রাখা বন্ধ করুন' : 'অরিজিনাল রাখা চালু করুন' }}</button>
        </form>
        <p class="mt-3 rounded-lg bg-slate-50 p-3 text-xs text-slate-600">
            💾 আগের জমে থাকা বড় ছবিগুলো ছোট করতে সার্ভারে একবার চালান:
            <code style="background:#e2e8f0;padding:1px 6px;border-radius:4px;">php artisan photos:shrink</code>
            (আগে <code style="background:#e2e8f0;padding:1px 6px;border-radius:4px;">--dry-run</code> দিয়ে দেখে নিতে পারেন কতটা সাশ্রয় হবে।)
        </p>
    </div>

    <div class="mt-6 rounded-xl border border-slate-200 bg-white p-5">
        <div class="font-semibold">আপডেটের ছবি (ঠিক হয়েছে / এখনো আছে / সরেজমিনে যাচাই)</div>
        <p class="mt-1 text-sm text-slate-600">চালু থাকলে নাগরিক ও স্বেচ্ছাসেবক আপডেটের সঙ্গে ছবি দিতে পারবেন। অনুমোদনের আগে কেউ ছবি দেখবে না। বন্ধ করলে নতুন ছবি নেওয়া হবে না; আগে অনুমোদিত ছবি দেখা যাবে।</p>
        <p class="mt-2 text-sm">এখন: <strong style="color:{{ $__upOn ? '#166534' : '#b91c1c' }}">{{ $__upOn ? 'চালু' : 'বন্ধ' }}</strong></p>
        <form method="POST" action="{{ route('photoreview.toggle') }}" class="mt-3">
            @csrf
            <input type="hidden" name="enabled" value="{{ $__upOn ? 0 : 1 }}">
            <button type="submit" style="background:{{ $__upOn ? '#b91c1c' : '#16a34a' }};color:#fff;font-weight:600;border:0;border-radius:8px;padding:8px 16px">{{ $__upOn ? 'বন্ধ করুন' : 'চালু করুন' }}</button>
        </form>
    </div>
</div>
@endsection