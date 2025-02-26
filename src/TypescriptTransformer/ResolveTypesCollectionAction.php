<?php

namespace Laramix\Laramix\TypeScriptTransformer;

use Exception;
use Generator;
use Laramix\Laramix\Action;
use Laramix\Laramix\Laramix;
use Laramix\Laramix\LaramixComponent;
use ReflectionClass;
use Spatie\TypeScriptTransformer\Actions\ResolveClassesInPhpFileAction;

class ResolveTypesCollectionAction extends \Spatie\TypeScriptTransformer\Actions\ResolveTypesCollectionAction
{
    protected function resolveIterator(array $paths): Generator
    {
        LaramixComponent::$NOCACHE = true;

        $paths = array_map(
            fn (string $path) => is_dir($path) ? $path : dirname($path),
            $paths
        );

        foreach ($this->finder->in($paths) as $fileInfo) {
            try {

                $classes = (new ResolveClassesInPhpFileAction)->execute($fileInfo);

                if (collect(['ts', 'tsx', 'jsx', 'js', 'php', 'mix'])->contains($fileInfo->getExtension()) && ! str($fileInfo->getFilename())->startsWith('.') && str($fileInfo->getPath())->contains(app(Laramix::class)->routeDirectory())) {

                    $filename = LaramixComponent::nameToNamespace($fileInfo->getFilenameWithoutExtension());
                    $component = new LaramixComponent($fileInfo->getRealPath(), $filename);

                    foreach ($component->classes() as $name => $class) {
                        yield $name => new ReflectionClass($name);
                    }

                    continue;
                }
                foreach ($classes as $name) {
                    yield $name => new ReflectionClass($name);
                }
            } catch (Exception $exception) {

            }
        }
        yield 'LaramixAction' => new ReflectionClass(Action::class);
    }
}
