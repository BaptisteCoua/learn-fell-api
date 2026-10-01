<?php

namespace Functional\Catalog\Tests\Unit;

use Functional\Catalog\Enums\SubjectStatus;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Feature 004: a withheld subject is no more public than a draft.
 */
class SubjectStatusTest extends TestCase
{
    /**
     * @return array<string, array{SubjectStatus, bool}>
     */
    public static function statuses(): array
    {
        return [
            'draft' => [SubjectStatus::Draft, false],
            'published' => [SubjectStatus::Published, true],
            'retired' => [SubjectStatus::Retired, false],
            'withheld' => [SubjectStatus::Withheld, false],
        ];
    }

    #[DataProvider('statuses')]
    public function test_only_a_published_subject_is_public(SubjectStatus $status, bool $isPublic): void
    {
        $this->assertSame($isPublic, $status->isPublic());
    }
}
