<?php

namespace Laramix\Laramix;

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
    }
}
