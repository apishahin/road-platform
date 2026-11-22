{{-- Appearance editor (font / size / colours / name / logo / issue view) — merged into the"Homepage & site" page. Posts still go to admin.appearance.update (controller untouched). --}}
@php
    $fontLabels = [
        'system'      => 'সিস্টেম ফন্ট (ডিফল্ট, বাইরের সার্ভার লাগে না)',
        'noto_google' => 'Noto Sans Bengali (নিজস্ব সার্ভার থেকে লোড হয় — ইন্টারনেট ছাড়াও কাজ করে)',
        'hind_google' => 'Hind Siliguri (নিজস্ব সার্ভার থেকে লোড হয় — ইন্টারনেট ছাড়াও কাজ করে)',
        'custom'      => 'নিজের আপলোড করা ফন্ট',
    ];
    $sizeLabels = ['normal' => 'স্বাভাবিক', 'large' => 'একটু বড়', 'xlarge' => 'বড়'];
    $curFont = old('font', $ap['font']);
    $curSize = old('size', $ap['size']);
@endphp

    @if (session('status'))
        <div class="mt-4 rounded-lg bg-green-50 p-3 text-sm text-green-800">{{ session('status') }}</div>
    @endif
    @if (session('warning'))
        <div class="mt-4 rounded-lg bg-amber-50 p-3 text-sm text-amber-800">{{ session('warning') }}</div>
    @endif
    @if ($errors->any())
        <div class="mt-4 rounded-lg bg-red-50 p-3 text-sm text-red-800">
            <ul class="list-disc pl-5">
                @foreach ($errors->all() as $e)
                    <li>{{ $e }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('admin.appearance.update') }}" enctype="multipart/form-data" class="mt-6 space-y-6">
        @csrf

        {{-- Font --}}
        <section class="rounded-xl border border-slate-200 bg-white p-5">
            <h2 class="text-lg font-semibold text-slate-900">ফন্ট</h2>
            <div class="mt-3 space-y-2">
                @foreach ($fontLabels as $key => $label)
                    <label class="flex items-start gap-2 text-sm">
                        <input type="radio" name="font" value="{{ $key }}" class="mt-1" @checked($curFont === $key)>
                        <span>{{ $label }}</span>
                    </label>
                @endforeach
            </div>

            <div class="mt-4 text-sm">
                <label class="block font-medium text-slate-700">নিজের ফন্ট আপলোড (woff2, woff, ttf, otf; সর্বোচ্চ ২ MB)</label>
                <input type="file" name="font_file" accept=".woff2,.woff,.ttf,.otf" class="mt-1 block text-sm">
                @if ($ap['font_file'])
                    <p class="mt-1 text-xs text-slate-500">এখন আপলোড করা আছে: {{ $ap['font_file'] }}</p>
                @endif
                <p class="mt-1 text-xs text-slate-500">ফাইল বেছে সংরক্ষণ করলে নিজে থেকেই "নিজের ফন্ট" চালু হবে। ফন্টটি ওয়েবসাইটে ব্যবহারের লাইসেন্স আছে কি না নিশ্চিত হয়ে নিন।</p>
            </div>
        </section>

        {{-- Size --}}
        <section class="rounded-xl border border-slate-200 bg-white p-5">
            <h2 class="text-lg font-semibold text-slate-900">লেখার আকার</h2>
            <div class="mt-3 flex flex-wrap gap-4">
                @foreach ($sizeLabels as $key => $label)
                    <label class="flex items-center gap-2 text-sm">
                        <input type="radio" name="size" value="{{ $key }}" @checked($curSize === $key)>
                        <span>{{ $label }}</span>
                    </label>
                @endforeach
            </div>
        </section>

        {{-- Issue page style --}}
<section class="rounded-xl border border-slate-200 bg-white p-5">
    <h2 class="text-lg font-semibold text-slate-900">ইস্যু পেজের ধরন</h2>
    <p class="mt-1 text-xs text-slate-500">সাধারণ দর্শক ইস্যুর বিস্তারিত পেজ কেমন দেখবে।</p>
    <div class="mt-3 space-y-2">
        <label class="flex items-start gap-2 text-sm">
            <input type="radio" name="issue_view" value="simple" class="mt-1" @checked(($ap['issue_view'] ?? 'simple') === 'simple')>
            <span><b>সহজ</b> (ডিফল্ট) — উপরে "এক নজরে" সারাংশ, সহজ ভাষা; সাধারণ মানুষের জন্য।</span>
        </label>
        <label class="flex items-start gap-2 text-sm">
            <input type="radio" name="issue_view" value="detailed" class="mt-1" @checked(($ap['issue_view'] ?? 'simple') === 'detailed')>
            <span><b>বিস্তারিত</b> — আগের পেজ, কর্তৃপক্ষের বাক্সসহ।</span>
        </label>
    </div>
</section>

{{-- Global map display --}}
<section class="rounded-xl border border-slate-200 bg-white p-5">
    <h2 class="text-lg font-semibold text-slate-900">মানচিত্র প্রদর্শন</h2>
    <p class="mt-1 text-xs text-slate-500">এই পছন্দটি হোম, রিপোর্ট, ইস্যু, মডারেশন ও কাজের কেন্দ্রের সব মানচিত্রে প্রযোজ্য। রিপোর্টের স্থান সব সময় শুধু বাংলাদেশের ভেতরেই গ্রহণ করা হবে।</p>
    <div class="mt-3 space-y-2">
        <label class="flex items-start gap-2 text-sm">
            <input type="radio" name="map_display" value="bangladesh" class="mt-1" @checked(old('map_display', $ap['map_display'] ?? 'bangladesh') === 'bangladesh')>
            <span><b>শুধু বাংলাদেশ</b> (ডিফল্ট) — বাংলাদেশের বাইরের OSM অংশ আড়াল থাকবে এবং মানচিত্রটি সম্পূর্ণ দেশের সীমানায় ফিট হবে।</span>
        </label>
        <label class="flex items-start gap-2 text-sm">
            <input type="radio" name="map_display" value="world" class="mt-1" @checked(old('map_display', $ap['map_display'] ?? 'bangladesh') === 'world')>
            <span><b>বিশ্ব মানচিত্র</b> — আগের মতো পুরো পৃথিবী দেখা ও প্যান করা যাবে; কিন্তু বাংলাদেশের বাইরে রিপোর্ট/GPS/API স্থান গ্রহণ করা হবে না।</span>
        </label>
    </div>
</section>

{{-- Colours --}}
        <section class="rounded-xl border border-slate-200 bg-white p-5">
            <h2 class="text-lg font-semibold text-slate-900">রং</h2>
            <div class="mt-3 grid gap-4 sm:grid-cols-3">
                <label class="text-sm">
                    <span class="block font-medium text-slate-700">মূল রং (বোতাম, লিংক)</span>
                    <input type="color" name="brand" value="{{ old('brand', $ap['brand']) }}" class="mt-1 h-10 w-full cursor-pointer rounded border border-slate-300">
                </label>
                <label class="text-sm">
                    <span class="block font-medium text-slate-700">হেডারের পটভূমি</span>
                    <input type="color" name="header_bg" value="{{ old('header_bg', $ap['header_bg']) }}" class="mt-1 h-10 w-full cursor-pointer rounded border border-slate-300">
                </label>
                <label class="text-sm">
                    <span class="block font-medium text-slate-700">হেডারের লেখা</span>
                    <input type="color" name="header_text" value="{{ old('header_text', $ap['header_text']) }}" class="mt-1 h-10 w-full cursor-pointer rounded border border-slate-300">
                </label>
            </div>
            <p class="mt-2 text-xs text-slate-500">ডিফল্ট: মূল রং {{ $apd['brand'] }}, হেডারের পটভূমি {{ $apd['header_bg'] }}, লেখা {{ $apd['header_text'] }}।</p>
            {{-- Brand colour presets (one click) --}}
            <div class="mt-3 flex flex-wrap items-center gap-2 text-xs text-slate-500">
                <span class="font-medium">প্রিসেট:</span>
                <button type="button" onclick="this.closest('section').querySelector('input[name=brand]').value='#16a34a'"
                        class="inline-flex items-center gap-1.5 rounded-full border border-slate-200 bg-white px-3 py-1 font-semibold text-slate-700 hover:bg-slate-50">
                    <span class="size-3.5 rounded-full" style="background:#16a34a"></span> সবুজ
                </button>
                <button type="button" onclick="this.closest('section').querySelector('input[name=brand]').value='#ea580c'"
                        class="inline-flex items-center gap-1.5 rounded-full border border-slate-200 bg-white px-3 py-1 font-semibold text-slate-700 hover:bg-slate-50">
                    <span class="size-3.5 rounded-full" style="background:#ea580c"></span> কমলা
                </button>
                <span>(ক্লিক করার পর সংরক্ষণ করতে ভুলবেন না)</span>
            </div>
        </section>

        {{-- Name and logo --}}
        <section class="rounded-xl border border-slate-200 bg-white p-5">
            <h2 class="text-lg font-semibold text-slate-900">সাইটের নাম ও লোগো</h2>
            <div class="mt-3 grid gap-4 sm:grid-cols-2">
                <label class="text-sm">
                    <span class="block font-medium text-slate-700">নাম (বাংলা)</span>
                    <input type="text" name="name_bn" maxlength="60" value="{{ old('name_bn', $ap['name_bn']) }}" placeholder="ফাঁকা থাকলে ডিফল্ট নাম" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2">
                </label>
                <label class="text-sm">
                    <span class="block font-medium text-slate-700">Name (English)</span>
                    <input type="text" name="name_en" maxlength="60" value="{{ old('name_en', $ap['name_en']) }}" placeholder="Empty = default name" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2">
                </label>
                <label class="text-sm">
                    <span class="block font-medium text-slate-700">ট্যাগলাইন (বাংলা) — নামের নিচে ছোট লাইন</span>
                    <input type="text" name="tagline_bn" maxlength="120" value="{{ old('tagline_bn', $ap['tagline_bn'] ?? '') }}" placeholder="ফাঁকা থাকলে দেখানো হয় না" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2">
                </label>
                <label class="text-sm">
                    <span class="block font-medium text-slate-700">Tagline (English) — small line under the name</span>
                    <input type="text" name="tagline_en" maxlength="120" value="{{ old('tagline_en', $ap['tagline_en'] ?? '') }}" placeholder="Empty = hidden" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2">
                </label>
            </div>

            <div class="mt-4 text-sm">
                <label class="block font-medium text-slate-700">লোগো (PNG, JPG বা WebP; সর্বোচ্চ ১ MB)</label>
                @if ($ap['logo'])
                    <img src="{{ asset(\App\Support\AppearanceSettings::DIR . '/' . $ap['logo']) }}" alt="" class="mt-2 h-12 w-auto rounded border border-slate-200 bg-slate-50 p-1">
                    <label class="mt-2 flex items-center gap-2 text-xs text-slate-600">
                        <input type="checkbox" name="remove_logo" value="1"> বর্তমান লোগো সরান
                    </label>
                @endif
                <input type="file" name="logo" accept=".png,.jpg,.jpeg,.webp" class="mt-2 block text-sm">
                <p class="mt-1 text-xs text-slate-500">SVG নেওয়া হয় না। হেডারে উচ্চতা ৩২ পিক্সেলে দেখানো হয়।</p>
            </div>
        </section>

        <button type="submit" class="rounded-lg bg-brand-600 px-5 py-2 text-sm font-semibold text-white hover:bg-brand-700">সংরক্ষণ করুন</button>
    </form>

    <form method="POST" action="{{ route('admin.appearance.reset') }}" class="mt-6 border-t border-slate-200 pt-6"
          onsubmit="return confirm('সব চেহারা-সেটিং ও আপলোড করা ফন্ট/লোগো মুছে ডিফল্টে ফেরানো হবে। নিশ্চিত?')">
        @csrf
        <button type="submit" class="rounded-lg border border-red-300 px-4 py-2 text-sm font-medium text-red-700 hover:bg-red-50">ডিফল্টে ফেরান</button>
    </form>
