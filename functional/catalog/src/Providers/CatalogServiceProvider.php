<?php

namespace Functional\Catalog\Providers;

use Functional\Catalog\Access\Controls\CategoryControl;
use Functional\Catalog\Access\Controls\QuestionControl;
use Functional\Catalog\Access\Controls\SubjectControl;
use Functional\Catalog\Access\Controls\TagControl;
use Functional\Catalog\Database\Seeders\CatalogSeeder;
use Functional\Catalog\Events\SubjectDeleting;
use Functional\Catalog\Events\SubjectSaving;
use Functional\Catalog\Events\SubjectTagsChanged;
use Functional\Catalog\Listeners\DeleteSubjectQuestions;
use Functional\Catalog\Listeners\RefreshSubjectSearchDocument;
use Functional\Catalog\Models\Category;
use Functional\Catalog\Models\Question;
use Functional\Catalog\Models\Subject;
use Functional\Catalog\Models\Tag;
use Functional\Catalog\Policies\CategoryPolicy;
use Functional\Catalog\Policies\QuestionPolicy;
use Functional\Catalog\Policies\SubjectPolicy;
use Functional\Catalog\Policies\TagPolicy;
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

        (new Access)->addControls([new SubjectControl, new QuestionControl, new CategoryControl, new TagControl]);

        Gate::policy(Subject::class, SubjectPolicy::class);
        Gate::policy(Question::class, QuestionPolicy::class);
        Gate::policy(Category::class, CategoryPolicy::class);
        Gate::policy(Tag::class, TagPolicy::class);

        Event::listen(SubjectSaving::class, [RefreshSubjectSearchDocument::class, 'handleSubjectSaving']);
        Event::listen(SubjectDeleting::class, DeleteSubjectQuestions::class);
        Event::listen(SubjectTagsChanged::class, [RefreshSubjectSearchDocument::class, 'handleSubjectTagsChanged']);
    }

    public function register(): void
    {
        $this->overrideConfigFrom(__DIR__.'/../../config/purify.php', 'purify');
    }
}
