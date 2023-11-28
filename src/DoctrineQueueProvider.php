<?php

namespace Digbang\SafeQueue;

use Illuminate\Contracts\Support\DeferrableProvider;
use Illuminate\Support\ServiceProvider;
use Digbang\SafeQueue\Console\WorkCommand;
use Illuminate\Contracts\Cache\Repository as Cache;

/**
 * @codeCoverageIgnore
 */
class DoctrineQueueProvider extends ServiceProvider implements DeferrableProvider
{
    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register(): void
    {
        if (!$this->isLumen()) {
            $this->publishes([
                __DIR__ . '/../config/safequeue.php' => config_path('safequeue.php'),
            ], 'safequeue');
        }

        $this->mergeConfigFrom(
            __DIR__ . '/../config/safequeue.php',
            'safequeue'
        );

        $this->registerWorker();
    }

    public function boot(): void
    {
        $this->commands('command.safeQueue.work');
    }

    /**
     * @return void
     */
    protected function registerWorker(): void
    {
        $this->registerWorkCommand();

        $this->app->singleton('safeQueue.worker', function ($app) {
            $isDownForMaintenance = function () {
                return $this->app->isDownForMaintenance();
            };

            return new Worker(
                $app['queue'],
                $app['events'],
                $app['Doctrine\Persistence\ManagerRegistry'],
                $app['Illuminate\Contracts\Debug\ExceptionHandler'],
                $isDownForMaintenance
            );
        });
    }

    /**
     * @return void
     */
    protected function registerWorkCommand(): void
    {
        $this->app->singleton('command.safeQueue.work', function ($app) {
            return new WorkCommand(
                $app['safeQueue.worker'],
                $app[Cache::class],
                $app['config']->get('safequeue')
            );
        });
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides(): array
    {
        return [
            'safeQueue.worker',
            'command.safeQueue.work'
        ];
    }

    /**
     * @return bool
     */
    protected function isLumen(): bool
    {
        return str_contains($this->app->version(), 'Lumen');
    }
}
