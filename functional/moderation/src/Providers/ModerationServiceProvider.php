<?php

namespace Functional\Moderation\Providers;

use Functional\Catalog\Events\SubjectDeleting;
use Functional\Moderation\Access\Controls\ModerationDecisionControl;
use Functional\Moderation\Access\Controls\ReportControl;
use Functional\Moderation\Listeners\DeleteReportsOfDeletedSubject;
use Functional\Moderation\Models\ModerationDecision;
use Functional\Moderation\Models\Report;
use Functional\Moderation\Policies\ModerationDecisionPolicy;
use Functional\Moderation\Policies\ReportPolicy;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Lomkit\Access\Access;
use Xefi\LaravelOSDD\LayerServiceProvider;

class ModerationServiceProvider extends LayerServiceProvider
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

        (new Access)->addControls([new ReportControl, new ModerationDecisionControl]);

        Gate::policy(Report::class, ReportPolicy::class);
        Gate::policy(ModerationDecision::class, ModerationDecisionPolicy::class);

        Event::listen(SubjectDeleting::class, DeleteReportsOfDeletedSubject::class);
    }
}
