<?php

namespace Functional\Catalog\Tests\Feature;

use Functional\Catalog\Models\Category;
use Functional\Catalog\Models\Subject;
use Functional\Catalog\Tests\Concerns\SearchesCatalog;
use Functional\Catalog\Tests\Concerns\WritesCatalog;
use Functional\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * FR-008 to FR-010 — user story 4.
 */
class CategoryManagementTest extends TestCase
{
    use RefreshDatabase, SearchesCatalog, WritesCatalog;

    private function admin(): User
    {
        return tap(User::factory()->create(), fn (User $user) => $user->givePermissionTo('categories.manage'));
    }

    public function test_an_administrator_creates_a_category_offered_to_everyone_last(): void
    {
        Category::factory()->create(['position' => 1]);

        $response = $this->createResource($this->admin(), 'categories', ['name' => 'Histoire']);

        $response->assertOk();
        $category = Category::query()->findOrFail($response->json('created.0'));
        $this->assertSame(2, $category->position);
        $this->assertContains($category->id, $this->returnedIds($this->searchResource('categories')));
    }

    public function test_someone_without_the_permission_cannot_write_categories(): void
    {
        $member = User::factory()->create();
        $category = Category::factory()->create();

        $this->createResource($member, 'categories', ['name' => 'Histoire'])->assertForbidden();
        $this->updateResource($member, 'categories', $category->id, ['name' => 'Autre'])->assertForbidden();
        $this->deleteResource($member, 'categories', [$category->id])->assertForbidden();
        $this->actingAs($member)->postJson('/api/categories/actions/reorder', [
            'fields' => [['name' => 'ids', 'value' => [$category->id]]],
        ])->assertForbidden();
    }

    public function test_a_name_is_unique_ignoring_case_and_accents(): void
    {
        $admin = $this->admin();
        Category::factory()->create(['name' => 'Géographie']);
        $other = Category::factory()->create(['name' => 'Histoire']);

        $this->createResource($admin, 'categories', ['name' => '  GEOGRAPHIE '])
            ->assertUnprocessable()
            ->assertJson(['code' => 'category_name_taken']);
        $this->updateResource($admin, 'categories', $other->id, ['name' => 'géographie'])
            ->assertJson(['code' => 'category_name_taken']);
        $this->updateResource($admin, 'categories', $other->id, ['name' => 'HISTOIRE'])->assertOk();
    }

    public function test_a_category_holding_subjects_cannot_be_deleted(): void
    {
        $admin = $this->admin();
        $category = Category::factory()->create(['name' => 'Langues']);
        Subject::factory()->for($category)->create();
        Subject::factory()->retired()->for($category)->create();

        $response = $this->deleteResource($admin, 'categories', [$category->id]);

        $response->assertUnprocessable()->assertJson(['code' => 'category_not_empty', 'subjects_count' => 2]);
        $this->assertStringContainsString('2 sujets', $response->json('message'));
        $this->assertModelExists($category);
    }

    public function test_an_empty_category_is_deleted(): void
    {
        $category = Category::factory()->create();

        $this->deleteResource($this->admin(), 'categories', [$category->id])->assertOk();

        $this->assertModelMissing($category);
    }

    public function test_an_administrator_reorders_the_categories(): void
    {
        [$first, $second, $third] = Category::factory()->count(3)->sequence(['position' => 1], ['position' => 2], ['position' => 3])->create();

        $this->actingAs($this->admin())->postJson('/api/categories/actions/reorder', [
            'fields' => [['name' => 'ids', 'value' => [$third->id, $first->id, $second->id]]],
        ])->assertOk();

        $this->assertSame([$third->id, $first->id, $second->id], Category::query()->orderBy('position')->pluck('id')->all());
    }
}
