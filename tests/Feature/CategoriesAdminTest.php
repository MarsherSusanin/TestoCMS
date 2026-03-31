<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\CategoryTranslation;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CategoriesAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_categories_index_renders_empty_state_for_superadmin(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $superadmin = $this->makeUser('categories-empty@testocms.local', 'superadmin');

        $this->actingAs($superadmin)
            ->get('/admin/categories')
            ->assertOk()
            ->assertSee('Категории')
            ->assertSee('Пока нет категорий')
            ->assertSee('Создать категорию');
    }

    public function test_categories_index_renders_existing_category_rows_without_parse_error(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $superadmin = $this->makeUser('categories-list@testocms.local', 'superadmin');

        $category = Category::query()->create([
            'is_active' => true,
        ]);

        CategoryTranslation::query()->create([
            'category_id' => $category->id,
            'locale' => 'ru',
            'title' => 'Новости',
            'slug' => 'novosti',
        ]);

        $this->actingAs($superadmin)
            ->get('/admin/categories')
            ->assertOk()
            ->assertSee('Новости')
            ->assertSee('/ru/category/novosti')
            ->assertSee('Редактировать');
    }

    private function makeUser(string $email, string $role): User
    {
        $user = User::query()->create([
            'name' => ucfirst($role),
            'login' => str_replace(['@', '.'], '_', explode('@', $email)[0]).'_'.random_int(10, 999),
            'email' => $email,
            'password' => Hash::make('password'),
            'status' => 'active',
        ]);

        $user->assignRole($role);

        return $user;
    }
}
