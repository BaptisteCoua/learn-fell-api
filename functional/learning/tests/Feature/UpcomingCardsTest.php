<?php

namespace Functional\Learning\Tests\Feature;

use Carbon\CarbonImmutable;
use Functional\Catalog\Enums\SubjectStatus;
use Functional\Catalog\Models\QuestionImage;
use Functional\Catalog\Models\Subject;
use Functional\Learning\Models\CardProgress;
use Functional\Learning\Tests\Concerns\ReviewsCards;
use Functional\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Feature 006, FR-001 — the cards a device keeps for offline review: those due within 7 days.
 */
class UpcomingCardsTest extends TestCase
{
    use RefreshDatabase, ReviewsCards;

    protected function setUp(): void
    {
        parent::setUp();

        // 6 October in Tokyo, still 5 October in Montreal.
        $this->travelTo(CarbonImmutable::parse('2026-10-05 23:30', 'UTC'));
    }

    /**
     * @param  list<array{relation: string}>  $includes
     */
    private function upcomingCards(User $user, array $includes = []): TestResponse
    {
        return $this->actingAs($user)->postJson('/api/card-progress/search', [
            'search' => [
                'instructions' => [['name' => 'upcoming']],
                'includes' => $includes,
                'limit' => 100,
            ],
        ]);
    }

    /**
     * @return list<int>
     */
    private function upcomingIds(User $user): array
    {
        return collect($this->upcomingCards($user)->assertOk()->json('data'))->pluck('id')->all();
    }

    /**
     * @param  list<string>  $dueDates
     * @return list<CardProgress>
     */
    private function cardsDueOn(User $user, array $dueDates, ?Subject $subject = null): array
    {
        $subject ??= $this->publishedSubjectWithQuestions(count($dueDates));
        $this->learn($user, $subject)->assertOk();
        $cards = CardProgress::query()->where('user_id', $user->id)->where('subject_id', $subject->id)->orderBy('id')->get();

        foreach ($dueDates as $index => $dueDate) {
            $cards[$index]->update(['next_review_on' => $dueDate]);
        }

        return $cards->all();
    }

    /**
     * @return array<string, array{string, list<string>, list<int>}>
     */
    public static function horizons(): array
    {
        return [
            'Tokyo, already the 6th' => ['Asia/Tokyo', ['2026-10-06', '2026-10-13', '2026-10-14'], [0, 1]],
            'Montreal, still the 5th' => ['America/Montreal', ['2026-10-05', '2026-10-12', '2026-10-13'], [0, 1]],
        ];
    }

    /**
     * @param  list<string>  $dueDates  today, today + 7 and today + 8 in the account time zone
     * @param  list<int>  $expectedIndexes
     */
    #[DataProvider('horizons')]
    public function test_the_cards_due_within_seven_days_of_the_account_today_are_kept(string $timezone, array $dueDates, array $expectedIndexes): void
    {
        $user = User::factory()->create(['timezone' => $timezone]);
        $cards = $this->cardsDueOn($user, $dueDates);

        $this->assertSame(
            array_map(fn (int $index): int => $cards[$index]->id, $expectedIndexes),
            $this->upcomingIds($user),
        );
    }

    public function test_overdue_cards_are_kept_too(): void
    {
        $user = User::factory()->create(['timezone' => 'Europe/Paris']);
        [$overdue] = $this->cardsDueOn($user, ['2026-09-20']);

        $this->assertSame([$overdue->id], $this->upcomingIds($user));
    }

    public function test_the_cards_come_by_due_date_then_subject_then_question_order(): void
    {
        $user = User::factory()->create(['timezone' => 'Europe/Paris']);
        $first = $this->publishedSubjectWithQuestions(2);
        $second = $this->publishedSubjectWithQuestions(2);
        [$firstA, $firstB] = $this->cardsDueOn($user, ['2026-10-08', '2026-10-06'], $first);
        [$secondA, $secondB] = $this->cardsDueOn($user, ['2026-10-06', '2026-10-06'], $second);
        $first->questions()->where('id', $firstB->question_id)->update(['position' => 10]);
        $second->questions()->where('id', $secondA->question_id)->update(['position' => 10]);

        $this->assertSame([$firstB->id, $secondB->id, $secondA->id, $firstA->id], $this->upcomingIds($user));
    }

    /**
     * @return array<string, array{SubjectStatus}>
     */
    public static function unpublishedStatuses(): array
    {
        return [
            'draft' => [SubjectStatus::Draft],
            'retired' => [SubjectStatus::Retired],
            'withheld' => [SubjectStatus::Withheld],
        ];
    }

    #[DataProvider('unpublishedStatuses')]
    public function test_the_cards_of_an_unpublished_subject_are_left_out(SubjectStatus $status): void
    {
        $user = User::factory()->create(['timezone' => 'Europe/Paris']);
        $subject = $this->publishedSubjectWithQuestions(2);
        $this->cardsDueOn($user, ['2026-10-06', '2026-10-07'], $subject);

        $subject->forceFill(['status' => $status])->save();

        $this->assertSame([], $this->upcomingIds($user));
    }

    public function test_the_cards_of_another_account_are_left_out(): void
    {
        $user = User::factory()->create(['timezone' => 'Europe/Paris']);
        $other = User::factory()->create(['timezone' => 'Europe/Paris']);
        $subject = $this->publishedSubjectWithQuestions(1);
        [$own] = $this->cardsDueOn($user, ['2026-10-06'], $subject);
        $this->cardsDueOn($other, ['2026-10-06'], $subject);

        $this->assertSame([$own->id], $this->upcomingIds($user));
    }

    public function test_a_card_comes_with_its_question_subject_and_image_descriptions(): void
    {
        Storage::fake(config('catalog.images.disk'));
        $user = User::factory()->create(['timezone' => 'Europe/Paris']);
        $subject = $this->publishedSubjectWithQuestions(1);
        $image = QuestionImage::factory()->attachedTo($subject->questions()->sole(), 0)->create();
        $this->cardsDueOn($user, ['2026-10-09'], $subject);

        $response = $this->upcomingCards($user, [
            ['relation' => 'question'],
            ['relation' => 'question.images'],
            ['relation' => 'subject'],
        ]);

        $response->assertOk();
        $this->assertNotEmpty($response->json('data.0.question.recto_html'));
        $this->assertSame($subject->id, $response->json('data.0.subject.id'));
        $this->assertSame($image->alt, $response->json('data.0.question.images.0.alt'));
    }
}
