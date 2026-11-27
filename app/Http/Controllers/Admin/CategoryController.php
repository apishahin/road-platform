<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** Admin-managed two-level reporting taxonomy. */
class CategoryController extends Controller
{
    private function guard(Request $request): void
    {
        abort_unless($request->user()?->role === 'admin', 403);
    }

    public function index(Request $request)
    {
        $this->guard($request);

        return view('admin.categories.index', [
            'parents' => Category::query()->whereNull('parent_id')->with('children')->orderBy('sort')->orderBy('id')->get(),
            'orphans' => Category::query()->whereNotNull('parent_id')->whereDoesntHave('parent')->orderBy('sort')->get(),
        ]);
    }

    public function store(Request $request, AuditService $audit)
    {
        $this->guard($request);
        $data = $this->validated($request);
        $parentId = $data['parent_id'] ?? null;
        $isLeaf = $parentId !== null;

        $category = Category::create([
            'parent_id' => $parentId,
            'slug' => Str::lower($data['slug']),
            'name_bn' => trim($data['name_bn']),
            'name_en' => trim((string) ($data['name_en'] ?? '')) ?: null,
            'sort' => (int) ($data['sort'] ?? 0),
            'is_active' => $request->boolean('is_active'),
            // A main category is only a grouping label. Existing leaves keep
            // their identity, and only leaves can be selected on a report.
            'is_reportable' => $isLeaf && $request->boolean('is_reportable'),
        ]);
        $audit->log($request->user(), 'category.created', 'category', (string) $category->id, [
            'parent_id' => $category->parent_id,
            'slug' => $category->slug,
            'reportable' => $category->is_reportable,
        ]);

        return back()->with('status', __('core.category_saved'));
    }

    public function update(Request $request, Category $category, AuditService $audit)
    {
        $this->guard($request);
        $data = $this->validated($request, $category);
        $parentId = $data['parent_id'] ?? null;
        if ($parentId && $category->children()->exists()) {
            throw ValidationException::withMessages(['parent_id' => __('core.category_parent_has_children')]);
        }

        $before = $category->only(['parent_id', 'slug', 'name_bn', 'name_en', 'sort', 'is_active', 'is_reportable']);
        $category->forceFill([
            'parent_id' => $parentId,
            'slug' => Str::lower($data['slug']),
            'name_bn' => trim($data['name_bn']),
            'name_en' => trim((string) ($data['name_en'] ?? '')) ?: null,
            'sort' => (int) ($data['sort'] ?? 0),
            'is_active' => $request->boolean('is_active'),
            'is_reportable' => $parentId !== null && $request->boolean('is_reportable'),
        ])->save();
        $audit->log($request->user(), 'category.updated', 'category', (string) $category->id, [
            'before' => $before,
            'after' => $category->only(['parent_id', 'slug', 'name_bn', 'name_en', 'sort', 'is_active', 'is_reportable']),
        ]);

        return back()->with('status', __('core.category_saved'));
    }

    private function validated(Request $request, ?Category $editing = null): array
    {
        $parents = Category::query()->whereNull('parent_id')->where('is_active', true);
        if ($editing) {
            $parents->where('id', '!=', $editing->id);
        }
        $parentIds = $parents->pluck('id')->all();

        return $request->validate([
            'parent_id' => ['nullable', 'integer', Rule::in($parentIds)],
            'slug' => ['required', 'string', 'max:50', 'regex:/^[a-z0-9-]+$/', Rule::unique('categories', 'slug')->ignore($editing?->id)],
            'name_bn' => ['required', 'string', 'max:100'],
            'name_en' => ['nullable', 'string', 'max:100'],
            'sort' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'is_active' => ['nullable', 'boolean'],
            'is_reportable' => ['nullable', 'boolean'],
        ]);
    }
}
