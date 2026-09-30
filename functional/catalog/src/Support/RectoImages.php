<?php

namespace Functional\Catalog\Support;

use Functional\Catalog\Models\Question;
use Functional\Catalog\Models\QuestionImage;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;

/**
 * The `relations.images` of a question's mutate (contracts/api.md §3): an `update` for every
 * image the recto keeps, a `detach` for every image taken off it. lomkit applies them to the
 * rows; this class checks them against the question before anything is written, and deletes
 * the images the recto no longer carries in the same transaction (research R6).
 */
class RectoImages
{
    private const KEPT = 'update';

    private const DETACHED = 'detach';

    /**
     * @param  list<array<string, mixed>>  $operations
     */
    private function __construct(private readonly bool $isSent, private readonly array $operations) {}

    /**
     * @param  array<string, mixed>  $mutation
     */
    public static function fromMutation(array $mutation): self
    {
        return new self(
            isset($mutation['relations']['images']),
            array_values($mutation['relations']['images'] ?? []),
        );
    }

    /**
     * Each kept image is the author's own pending image or already on this question, and only
     * an image of this question is taken off it (FR-001, research R6). The limit of 4 is a
     * validation rule (WithinRectoImageLimit).
     */
    public function authorize(Question $question): void
    {
        $images = QuestionImage::query()->findMany([...$this->keysOf(self::KEPT), ...$this->keysOf(self::DETACHED)])->keyBy('id');

        foreach ($this->operations as $operation) {
            $ability = match ($operation['operation']) {
                self::KEPT => 'attach',
                self::DETACHED => 'detach',
                default => throw new AuthorizationException,
            };

            Gate::authorize($ability, [QuestionImage::class, $question, $images->get($operation['key'])]);
        }
    }

    /**
     * How many images the recto will carry once saved: the listed ones, or, when none is listed,
     * those it has minus the detached ones.
     */
    public function countAfterSave(Question $question): int
    {
        $keptKeys = $this->keysOf(self::KEPT);

        if ($keptKeys !== []) {
            return count($keptKeys);
        }

        return $question->exists
            ? $question->images()->whereKeyNot($this->keysOf(self::DETACHED))->count()
            : 0;
    }

    /**
     * Detached images, and the images of the question left out of a non-empty list, are gone
     * for good (FR-005, FR-017). Each one is deleted as a model, so its files follow.
     */
    public function deleteDropped(Question $question): void
    {
        if (! $this->isSent) {
            return;
        }

        QuestionImage::query()->whereKey($this->keysOf(self::DETACHED))->get()->each->delete();

        $keptKeys = $this->keysOf(self::KEPT);

        if ($keptKeys !== []) {
            $question->images()->whereKeyNot($keptKeys)->get()->each->delete();
        }
    }

    /**
     * @return list<int>
     */
    private function keysOf(string $operation): array
    {
        return collect($this->operations)
            ->where('operation', $operation)
            ->pluck('key')
            ->map(fn (mixed $key): int => (int) $key)
            ->values()
            ->all();
    }
}
