<?php

namespace Laramix\Laramix\TypeScriptTransformer;

use Laramix\Laramix\LaramixComponent;
use Exception;
use Generator;
use ReflectionClass;
use Spatie\TypeScriptTransformer\Actions\ResolveClassesInPhpFileAction;

class ResolveTypesCollectionAction extends \Spatie\TypeScriptTransformer\Actions\ResolveTypesCollectionAction
{

    protected function resolveIterator(array $paths): Generator
    {


        $paths = array_map(
            fn (string $path) => is_dir($path) ? $path : dirname($path),
            $paths
        );

        foreach ($this->finder->in($paths) as $fileInfo) {
            try {

                $classes = (new ResolveClassesInPhpFileAction())->execute($fileInfo);

                if (collect(['ts', 'tsx', 'jsx','js'])->contains($fileInfo->getExtension())) {
                    $filename = LaramixComponent::nameToNamespace($fileInfo->getFilenameWithoutExtension());
                    $component = new LaramixComponent($fileInfo->getRealPath(), $filename );
                    foreach ($component->classes() as $name => $class) {
                        yield $name => $class;
                    }
                    continue;
                }
                foreach ($classes as $name) {
                    yield $name => new ReflectionClass($name);
                }
            } catch (Exception $exception) {

            }
        }
    }

}
