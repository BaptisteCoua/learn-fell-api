<?php

namespace Functional\Catalog\Tests\Feature;

use Functional\Catalog\Models\Subject;
use Functional\Catalog\Models\Tag;
use Functional\Catalog\Tests\Concerns\SearchesCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * FR-025 — search on title, description and tags, ignoring case and accents, from 2 characters.
 */
class SubjectSearchTest extends TestCase
{
    use RefreshDatabase, SearchesCatalog;

    /**
     * @return array<string, array{string}>
     */
    public static function matchingQueries(): array
    {
        return [
            'title without accent' => ['irreguliers'],
            'title with capitals and accent' => ['IRRÉGULIERS'],
            'word fragment' => ['irrég'],
            'description' => ['prétérit'],
            'tag' => ['grammaire'],
        ];
    }

    #[DataProvider('matchingQueries')]
    public function test_a_published_subject_is_found_by_its_title_description_or_tags(string $query): void
    {
        $subject = Subject::factory()->published()->create([
            'title' => 'Verbes irréguliers anglais',
            'description' => 'Base verbale, prétérit et participe passé.',
        ]);
        $subject->tags()->attach(Tag::factory()->create(['name' => 'Grammaire']));
        Subject::factory()->published()->create(['title' => 'Capitales du monde', 'description' => 'Europe et Asie.']);

        $response = $this->search($query);

        $response->assertOk();
        $this->assertSame([$subject->getKey()], $this->returnedIds($response));
    }

    public function test_a_search_with_no_match_returns_nothing(): void
    {
        Subject::factory()->published()->create(['title' => 'Verbes irréguliers anglais']);

        $this->assertSame([], $this->returnedIds($this->search('xylophone quantique')));
    }

    public function test_a_search_under_two_characters_is_refused(): void
    {
        $this->search('r')->assertUnprocessable();
    }

    private function search(string $query): TestResponse
    {
        return $this->searchResource('subjects', [
            'instructions' => [['name' => 'search', 'fields' => [['name' => 'q', 'value' => $query]]]],
        ]);
    }
}
