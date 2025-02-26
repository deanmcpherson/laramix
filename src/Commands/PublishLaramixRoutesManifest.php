<?php

namespace Laramix\Laramix\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Laramix\Laramix\Laramix;

class PublishLaramixRoutesManifest extends Command
{
    protected $signature = 'laramix:publish-routes-manifest
                            {--force : Force the operation to run when in production}
                            {--output= : Use another file to output}';

    protected $description = 'Map PHP structures to TypeScript';

    public function handle(
        Laramix $laramix
    ): int {
        $outputFile = $this->resolveOutputFile();

        $manifest = json_encode($laramix->routesManifest());
        if (File::exists($outputFile) && $manifest === File::get($outputFile)) {
            $this->info('No changes to the manifest file');
            return 0;
        }

        File::put($outputFile, json_encode($laramix->routesManifest()));

        return 0;
    }

    private function resolveOutputFile(): string
    {
        $path = $this->option('output');

        if ($path === null) {
            return config('laramix.manifest_path');
        }

        return resource_path($path);
    }
}
