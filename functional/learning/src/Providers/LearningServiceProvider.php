<?php

namespace Functional\Learning\Providers;

use Functional\Catalog\Events\QuestionCreated;
use Functional\Catalog\Events\QuestionDeleting;
use Functional\Catalog\Events\SubjectDeleting;
use Functional\Learning\Access\Controls\CardProgressControl;
use Functional\Learning\Access\Controls\LearningControl;
use Functional\Learning\Events\LearningCreated;
use Functional\Learning\Events\LearningDeleting;
use Functional\Learning\Listeners\CreateCardsForLearning;
use Functional\Learning\Listeners\DeleteCardsOfLearning;
use Functional\Learning\Listeners\DeleteSubjectLearnings;
use Functional\Learning\Listeners\QueueQuestionForLearners;
use Functional\Learning\Listeners\RemoveQuestionFromLearners;
use Functional\Learning\Models\CardProgress;
use Functional\Learning\Models\Learning;
use Functional\Learning\Policies\CardProgressPolicy;
use Functional\Learning\Policies\LearningPolicy;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Lomkit\Access\Access;
use Xefi\LaravelOSDD\LayerServiceProvider;

class LearningServiceProvider extends LayerServiceProvider
{
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');
        }

        $this->withRouting(
            api: __DIR__.'/../../routes/api.php',
            commands: __DIR__.'/../../routes/console.php',
        );

        (new Access)->addControls([new LearningControl, new CardProgressControl]);

        Gate::policy(Learning::class, LearningPolicy::class);
        Gate::policy(CardProgress::class, CardProgressPolicy::class);

        Event::listen(LearningCreated::class, CreateCardsForLearning::class);
        Event::listen(LearningDeleting::class, DeleteCardsOfLearning::class);
        Event::listen(QuestionCreated::class, QueueQuestionForLearners::class);
        Event::listen(QuestionDeleting::class, RemoveQuestionFromLearners::class);
        Event::listen(SubjectDeleting::class, DeleteSubjectLearnings::class);
    }
}
