<?php

namespace Functional\Catalog\Models;

use Functional\Catalog\Events\SubjectTagsChanged;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * The subject ↔ tag pivot, as a model so that attaching or detaching a tag raises an event
 * (the subject's search document depends on its tags).
 */
class SubjectTag extends Pivot
{
    protected $table = 'subject_tag';

    public $timestamps = false;

    /**
     * @var array<string, class-string>
     */
    protected $dispatchesEvents = [
        'created' => SubjectTagsChanged::class,
        'deleted' => SubjectTagsChanged::class,
    ];
}
