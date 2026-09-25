<?php

namespace Functional\Catalog\Listeners;

use Functional\Catalog\Events\SubjectSaving;
use Functional\Catalog\Events\SubjectTagsChanged;
use Functional\Catalog\Models\Subject;
use Functional\Catalog\Support\SubjectSearchDocument;

/**
 * Keeps subjects.search_document in step with the title, description and tags.
 */
class RefreshSubjectSearchDocument
{
    public function handleSubjectSaving(SubjectSaving $event): void
    {
        $event->subject->search_document = SubjectSearchDocument::for($event->subject);
    }

    public function handleSubjectTagsChanged(SubjectTagsChanged $event): void
    {
        $subject = Subject::query()->find($event->subjectTag->subject_id);

        if ($subject !== null) {
            $subject->forceFill(['search_document' => SubjectSearchDocument::for($subject)])->saveQuietly();
        }
    }
}
