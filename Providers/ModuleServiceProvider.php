<?php

namespace Modules\CattrClockInWebhook\Providers;

use CatEvent;
use Illuminate\Support\ServiceProvider;
use Modules\CattrClockInWebhook\Subscribers\EventObserver;

class ModuleServiceProvider extends ServiceProvider
{
    protected string $moduleName = 'CattrClockInWebhook';

    public function boot(): void
    {
        $this->loadMigrationsFrom(module_path($this->moduleName, 'Database/Migrations'));
    }

    public function register(): void
    {
        $this->mergeConfigFrom(
            module_path($this->moduleName, 'Config/config.php'),
            'cattr-clock-in-webhook'
        );
    }

    public static function registerEvents(): void
    {
        CatEvent::subscribe(EventObserver::class);
    }
}
