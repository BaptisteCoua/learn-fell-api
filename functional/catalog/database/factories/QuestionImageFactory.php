<?php

namespace Functional\Catalog\Database\Factories;

use Functional\Catalog\Models\Question;
use Functional\Catalog\Models\QuestionImage;
use Functional\Users\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Storage;

/**
 * An attached image by default, with a placeholder file for each of its variants.
 *
 * @extends Factory<QuestionImage>
 */
class QuestionImageFactory extends Factory
{
    protected $model = QuestionImage::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'question_id' => Question::factory(),
            'uploader_id' => User::factory(),
            'alt' => mb_substr(faker()->sentences(1), 0, 250),
            'position' => 0,
            'width' => 1600,
            'height' => faker()->number(600, 1600),
            'variant_widths' => [480, 960, 1600],
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (QuestionImage $image): void {
            foreach ($image->variant_widths as $width) {
                Storage::disk(config('catalog.images.disk'))->put(
                    "{$image->directory()}/{$width}.webp",
                    "variant {$width} of image {$image->getKey()}",
                );
            }
        });
    }

    /**
     * Sent, not yet attached to a question (FR-018).
     */
    public function pending(): static
    {
        return $this->state(fn (): array => [
            'question_id' => null,
            'alt' => null,
            'position' => null,
        ]);
    }

    public function attachedTo(Question $question, int $position): static
    {
        return $this->state(fn (): array => [
            'question_id' => $question->getKey(),
            'alt' => mb_substr(faker()->sentences(1), 0, faker()->number(1, 250)),
            'position' => $position,
        ]);
    }

    /**
     * A source narrower than the widest variant: never enlarged (research R3).
     */
    public function small(): static
    {
        return $this->state(fn (): array => [
            'width' => 700,
            'height' => 466,
            'variant_widths' => [480, 960],
        ]);
    }
}
