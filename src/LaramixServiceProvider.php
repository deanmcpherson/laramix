<?php

namespace Laramix\Laramix;

use Illuminate\Routing\Route;
use Laramix\Laramix\Commands\PublishLaramixRoutesManifest;
use Laramix\Laramix\Commands\TypeScriptTransformCommand;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class LaramixServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        /*
         * This class is a Package Service Provider
         *
         * More info: https://github.com/spatie/laravel-package-tools
         */
        $package
            ->name('laramix')
            ->hasConfigFile()
            ->hasCommand(TypeScriptTransformCommand::class)
            ->hasCommand(PublishLaramixRoutesManifest::class);
        
        Route::macro('asLaramixRoute', function(string $path) {
            $this->laramixRoute = $path;
            return $this;
        });

        Route::macro('laramixRoute', function() {
            if (isset($this->laramixRoute)) {
                return $this->laramixRoute;
            }
            return null;
        });
    }
}
