<?php

namespace Tests\Feature;

use App\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\CreatesUsers;
use Tests\TestCase;

class CategoryTest extends TestCase
{
    use RefreshDatabase, CreatesUsers;

    // ─── LIST ─────────────────────────────────────────────────────

    public function test_authenticated_user_can_list_categories(): void
    {
        $student = $this->createStudent();
        Category::factory()->count(3)->create(['created_by' => $student->id]);

        $this->actingAs($student)
            ->getJson('/api/categories')
            ->assertOk()
            ->assertJsonStructure(['data']);
    }

    public function test_unauthenticated_cannot_list_categories(): void
    {
        $this->getJson('/api/categories')->assertUnauthorized();
    }

    // ─── CREATE ───────────────────────────────────────────────────

    public function test_teacher_can_create_category(): void
    {
        $teacher = $this->createTeacher();

        $this->actingAs($teacher)
            ->postJson('/api/categories', [
                'name'        => 'Mathematics',
                'description' => 'Math topics',
            ])
            ->assertCreated()
            ->assertJsonPath('category.name', 'Mathematics');

        $this->assertDatabaseHas('categories', ['name' => 'Mathematics']);
    }

    public function test_admin_can_create_category(): void
    {
        $admin = $this->createAdmin();

        $this->actingAs($admin)
            ->postJson('/api/categories', ['name' => 'Science'])
            ->assertCreated();
    }

    public function test_student_cannot_create_category(): void
    {
        $student = $this->createStudent();

        $this->actingAs($student)
            ->postJson('/api/categories', ['name' => 'Hack'])
            ->assertForbidden();
    }

    public function test_category_requires_name(): void
    {
        $teacher = $this->createTeacher();

        $this->actingAs($teacher)
            ->postJson('/api/categories', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    }

    public function test_category_slug_is_auto_generated(): void
    {
        $teacher = $this->createTeacher();

        $this->actingAs($teacher)
            ->postJson('/api/categories', ['name' => 'My Category'])
            ->assertCreated();

        $this->assertDatabaseHas('categories', ['slug' => 'my-category']);
    }

    public function test_category_slug_must_be_unique(): void
    {
        $teacher = $this->createTeacher();
        Category::factory()->create(['slug' => 'unique-slug', 'created_by' => $teacher->id]);

        $this->actingAs($teacher)
            ->postJson('/api/categories', [
                'name' => 'Another Cat',
                'slug' => 'unique-slug',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['slug']);
    }

    // ─── SHOW ─────────────────────────────────────────────────────

    public function test_can_view_category(): void
    {
        $teacher  = $this->createTeacher();
        $category = Category::factory()->create(['created_by' => $teacher->id]);

        $this->actingAs($teacher)
            ->getJson("/api/categories/{$category->id}")
            ->assertOk()
            ->assertJsonPath('id', $category->id);
    }

    // ─── UPDATE ───────────────────────────────────────────────────

    public function test_teacher_can_update_own_category(): void
    {
        $teacher  = $this->createTeacher();
        $category = Category::factory()->create(['created_by' => $teacher->id]);

        $this->actingAs($teacher)
            ->putJson("/api/categories/{$category->id}", ['name' => 'Updated'])
            ->assertOk()
            ->assertJsonPath('category.name', 'Updated');
    }

    public function test_teacher_cannot_update_others_category(): void
    {
        $teacher1 = $this->createTeacher();
        $teacher2 = $this->createTeacher();
        $category = Category::factory()->create(['created_by' => $teacher1->id]);

        $this->actingAs($teacher2)
            ->putJson("/api/categories/{$category->id}", ['name' => 'Hack'])
            ->assertForbidden();
    }

    // ─── DELETE ───────────────────────────────────────────────────

    public function test_teacher_can_delete_empty_category(): void
    {
        $teacher  = $this->createTeacher();
        $category = Category::factory()->create(['created_by' => $teacher->id]);

        $this->actingAs($teacher)
            ->deleteJson("/api/categories/{$category->id}")
            ->assertOk();

        $this->assertDatabaseMissing('categories', ['id' => $category->id]);
    }

    public function test_cannot_delete_category_with_children(): void
    {
        $teacher  = $this->createTeacher();
        $parent   = Category::factory()->create(['created_by' => $teacher->id]);
        Category::factory()->create([
            'parent_id'  => $parent->id,
            'created_by' => $teacher->id,
        ]);

        $this->actingAs($teacher)
            ->deleteJson("/api/categories/{$parent->id}")
            ->assertUnprocessable();
    }
}
