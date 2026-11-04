<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Page;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PagesController extends Controller
{
    private function guard(): void
    {
        abort_unless(auth()->user()?->role === 'admin', 403);
    }

    public function index()
    {
        $this->guard();
        $pages = Page::orderBy('menu_order')->orderBy('id')->get();
        return view('admin.pages.index', ['pages' => $pages]);
    }

    public function create()
    {
        $this->guard();
        return view('admin.pages.form', ['page' => new Page(['menu_order' => 100])]);
    }

    public function store(Request $request, AuditService $audit)
    {
        $this->guard();
        $data = $this->validated($request, null);
        $page = Page::create($data);

        $audit->log($request->user(), 'page.created', 'page', (string) $page->id, [
            'slug' => $page->slug,
            'published' => $page->is_published,
            'in_menu' => $page->in_menu,
        ]);

        return redirect()->route('admin.pages.index')->with('status', 'পেজ তৈরি হয়েছে।');
    }

    public function edit(Page $page)
    {
        $this->guard();
        return view('admin.pages.form', ['page' => $page]);
    }

    public function update(Request $request, Page $page, AuditService $audit)
    {
        $this->guard();
        $data = $this->validated($request, $page);
        $page->update($data);

        $audit->log($request->user(), 'page.updated', 'page', (string) $page->id, [
            'slug' => $page->slug,
            'published' => $page->is_published,
            'in_menu' => $page->in_menu,
        ]);

        return redirect()->route('admin.pages.index')->with('status', 'পেজ সংরক্ষণ হয়েছে।');
    }

    public function destroy(Request $request, Page $page, AuditService $audit)
    {
        $this->guard();
        $slug = $page->slug;
        $id = (string) $page->id;
        $page->delete();

        $audit->log($request->user(), 'page.deleted', 'page', $id, ['slug' => $slug]);

        return redirect()->route('admin.pages.index')->with('status', 'পেজ মুছে ফেলা হয়েছে।');
    }

    private function validated(Request $request, ?Page $page): array
    {
        $data = $request->validate([
            'slug' => [
                'required', 'string', 'max:60',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('pages', 'slug')->ignore($page?->id),
            ],
            'title_bn' => ['required', 'string', 'max:150'],
            'title_en' => ['nullable', 'string', 'max:150'],
            'body_bn' => ['required', 'string', 'max:50000'],
            'body_en' => ['nullable', 'string', 'max:50000'],
            'menu_order' => ['nullable', 'integer', 'min:0', 'max:1000'],
        ], [
            'slug.regex' => 'লিংকের নাম শুধু ছোট ইংরেজি অক্ষর, সংখ্যা ও মাঝখানে হাইফেন (-) হতে পারে। যেমন: how-it-works',
            'slug.unique' => 'এই লিংকের নাম আগেই ব্যবহার হয়েছে।',
        ]);

        $data['is_published'] = $request->boolean('is_published');
        $data['in_menu'] = $request->boolean('in_menu');
        $data['menu_order'] = (int) ($data['menu_order'] ?? 100);

        if ($data['in_menu'] && $data['is_published']) {
            $others = Page::where('is_published', true)
                ->where('in_menu', true)
                ->when($page, fn ($q) => $q->where('id', '!=', $page->id))
                ->count();
            if ($others >= 6) {
                throw ValidationException::withMessages([
                    'in_menu' => 'মেনুতে সর্বোচ্চ ৬টি পেজ থাকতে পারে।',
                ]);
            }
        }

        return $data;
    }
}