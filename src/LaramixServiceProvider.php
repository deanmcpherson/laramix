<?php

namespace Laramix\Laramix;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Laramix\Laramix\Commands\LaramixCommand;
use Laramix\Laramix\Commands\TypeScriptTransformCommand;

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
            ->hasCommand(TypeScriptTransformCommand::class);
    }
}
