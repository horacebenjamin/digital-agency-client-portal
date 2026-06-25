<?php

namespace App\Providers;

use App\Models\Project;
use App\Models\ProjectFile;
use App\Policies\ProjectFilePolicy;
use App\Policies\ProjectPolicy;
use App\Support\FallbackMimeTypeGuesser;
use Illuminate\Filesystem\LocalFilesystemAdapter as LaravelLocalFilesystemAdapter;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use League\Flysystem\Filesystem;
use League\Flysystem\Local\LocalFilesystemAdapter;
use League\Flysystem\UnixVisibility\PortableVisibilityConverter;
use League\Flysystem\Visibility;
use League\MimeTypeDetection\ExtensionMimeTypeDetector;
use Symfony\Component\Mime\MimeTypes;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        MimeTypes::getDefault()->registerGuesser(new FallbackMimeTypeGuesser);

        $this->registerLocalFilesystemDriver();

        Gate::policy(Project::class, ProjectPolicy::class);
        Gate::policy(ProjectFile::class, ProjectFilePolicy::class);

        Vite::prefetch(concurrency: 3);
    }

    private function registerLocalFilesystemDriver(): void
    {
        Storage::extend('local', function ($app, array $config): LaravelLocalFilesystemAdapter {
            $visibility = PortableVisibilityConverter::fromArray(
                $config['permissions'] ?? [],
                $config['directory_visibility'] ?? $config['visibility'] ?? Visibility::PRIVATE,
            );

            $links = ($config['links'] ?? null) === 'skip'
                ? LocalFilesystemAdapter::SKIP_LINKS
                : LocalFilesystemAdapter::DISALLOW_LINKS;

            $adapter = new LocalFilesystemAdapter(
                $config['root'],
                $visibility,
                $config['lock'] ?? LOCK_EX,
                $links,
                new ExtensionMimeTypeDetector,
            );

            $driver = new Filesystem($adapter, Arr::only($config, [
                'directory_visibility',
                'disable_asserts',
                'retain_visibility',
                'temporary_url',
                'url',
                'visibility',
            ]));

            return (new LaravelLocalFilesystemAdapter($driver, $adapter, $config))
                ->shouldServeSignedUrls(
                    $config['serve'] ?? false,
                    fn () => $app['url'],
                );
        });
    }
}
