<?php

namespace Functional\Catalog\Providers;

use Functional\Catalog\Access\Controls\CategoryControl;
use Functional\Catalog\Access\Controls\QuestionControl;
use Functional\Catalog\Access\Controls\QuestionImageControl;
use Functional\Catalog\Access\Controls\SubjectControl;
use Functional\Catalog\Access\Controls\TagControl;
use Functional\Catalog\Database\Seeders\CatalogSeeder;
use Functional\Catalog\Events\QuestionDeleting;
use Functional\Catalog\Events\QuestionImageDeleted;
use Functional\Catalog\Events\SubjectDeleting;
use Functional\Catalog\Events\SubjectSaving;
use Functional\Catalog\Events\SubjectTagsChanged;
use Functional\Catalog\Listeners\DeleteQuestionImageFiles;
use Functional\Catalog\Listeners\DeleteQuestionImages;
use Functional\Catalog\Listeners\DeleteSubjectQuestions;
use Functional\Catalog\Listeners\EraseSubjectsOfUser;
use Functional\Catalog\Listeners\RefreshSubjectSearchDocument;
use Functional\Catalog\Listeners\RestoreWithheldSubjects;
use Functional\Catalog\Listeners\WithholdSubjectsOfLeavingAuthor;
use Functional\Catalog\Models\Category;
use Functional\Catalog\Models\Question;
use Functional\Catalog\Models\QuestionImage;
use Functional\Catalog\Models\Subject;
use Functional\Catalog\Models\Tag;
use Functional\Catalog\Policies\CategoryPolicy;
use Functional\Catalog\Policies\QuestionImagePolicy;
use Functional\Catalog\Policies\QuestionPolicy;
use Functional\Catalog\Policies\SubjectPolicy;
use Functional\Catalog\Policies\TagPolicy;
use Functional\Users\Events\AccountDeletionCancelled;
use Functional\Users\Events\AccountDeletionRequested;
use Functional\Users\Models\User;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Lomkit\Access\Access;
use Xefi\LaravelOSDD\LayerServiceProvider;

class CatalogServiceProvider extends LayerServiceProvider
{
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');
            $this->loadSeeders([CatalogSeeder::class], priority: 10);
        }

        $this->withRouting(
            api: __DIR__.'/../../routes/api.php',
            commands: __DIR__.'/../../routes/console.php',
        );

        (new Access)->addControls([new SubjectControl, new QuestionControl, new QuestionImageControl, new CategoryControl, new TagControl]);

        Gate::policy(Subject::class, SubjectPolicy::class);
        Gate::policy(Question::class, QuestionPolicy::class);
        Gate::policy(QuestionImage::class, QuestionImagePolicy::class);
        Gate::policy(Category::class, CategoryPolicy::class);
        Gate::policy(Tag::class, TagPolicy::class);

        Event::listen(SubjectSaving::class, [RefreshSubjectSearchDocument::class, 'handleSubjectSaving']);
        Event::listen(SubjectDeleting::class, DeleteSubjectQuestions::class);
        Event::listen(QuestionDeleting::class, DeleteQuestionImages::class);
        Event::listen(QuestionImageDeleted::class, DeleteQuestionImageFiles::class);
        Event::listen(SubjectTagsChanged::class, [RefreshSubjectSearchDocument::class, 'handleSubjectTagsChanged']);
        Event::listen(AccountDeletionRequested::class, WithholdSubjectsOfLeavingAuthor::class);
        Event::listen(AccountDeletionCancelled::class, RestoreWithheldSubjects::class);
        Event::listen('eloquent.deleting: '.User::class, EraseSubjectsOfUser::class);
    }

    public function register(): void
    {
        $this->overrideConfigFrom(__DIR__.'/../../config/purify.php', 'purify');
        $this->mergeConfigFrom(__DIR__.'/../../config/catalog.php', 'catalog');
    }
}
