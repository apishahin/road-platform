<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminCategoryTaxonomyTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::unguarded(fn () => User::create([
            'public_id' => (string) Str::ulid(),
            'phone' => '+88018' . random_int(10000000, 99999999),
            'role' => 'admin',
        ]));
    }

    public function test_admin_can_create_main_category_then_reportable_subcategory(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->post(route('admin.categories.store'), [
            'slug' => 'public-space', 'name_bn' => 'জনপরিসর', 'name_en' => 'Public space', 'sort' => 20,
            'is_active' => '1', 'is_reportable' => '1',
        ])->assertSessionHasNoErrors();
        $parent = Category::where('slug', 'public-space')->firstOrFail();
        $this->assertFalse($parent->is_reportable);

        $this->actingAs($admin)->post(route('admin.categories.store'), [
            'parent_id' => $parent->id, 'slug' => 'streetlight-out', 'name_bn' => 'বাতি নষ্ট', 'name_en' => 'Streetlight out', 'sort' => 1,
            'is_active' => '1', 'is_reportable' => '1',
        ])->assertSessionHasNoErrors();
        $this->assertTrue(Category::where('slug', 'streetlight-out')->firstOrFail()->is_reportable);
    }
}
