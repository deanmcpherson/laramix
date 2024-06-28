<?php

namespace Laramix\Laramix\Commands;

use Exception;
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

        File::put($outputFile, json_encode($laramix->routesManifest()));

        return 0;
    }



    private function resolveOutputFile(): string
    {
        $path = $this->option('output');

        if ($path === null) {
            return resource_path('js/laramix-routes.manifest.json');
        }

        return resource_path($path);
    }

}
