<?php

namespace Functional\Catalog\Listeners;

use Functional\Catalog\Events\QuestionImageDeleted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * The files of a deleted image go once its row is really gone: a rolled back transaction keeps
 * both (FR-017).
 */
class DeleteQuestionImageFiles
{
    public function handle(QuestionImageDeleted $event): void
    {
        $directory = $event->image->directory();

        DB::afterCommit(fn () => Storage::disk(config('catalog.images.disk'))->deleteDirectory($directory));
    }
}
