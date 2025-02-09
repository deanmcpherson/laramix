<?php

namespace Laramix\Laramix\TypeScriptTransformer;

use Illuminate\Support\Facades\File;
use Laramix\Laramix\LaramixComponent;
use Spatie\TypeScriptTransformer\Actions\FormatTypeScriptAction;
use Spatie\TypeScriptTransformer\Actions\PersistTypesCollectionAction;
use Spatie\TypeScriptTransformer\Structures\TypesCollection;
use Spatie\TypeScriptTransformer\TypeScriptTransformer as TypeScriptTransformerTypeScriptTransformer;
use Symfony\Component\Finder\Finder;

class TypeScriptTransformer extends TypeScriptTransformerTypeScriptTransformer
{
    private const hardcoded = <<<'EOF'
    declare namespace Laramix {
        export interface VisitOptions {
            preserveScroll?: boolean
            preserveState?: boolean
            only?: string[]
            replace?: boolean
            preserveQuery?: boolean
            preserveHash?: boolean
            headers?: Record<string, string>
            onError?: (error: Error) => void
            onSuccess?: (page: any) => void
            onCancel?: () => void
        }
    }
EOF;

    public function transform(): TypesCollection
    {
        $typesCollection = (new ResolveTypesCollectionAction(
            new Finder(),
            $this->config,
        ))->execute();

        (new PersistTypesCollectionAction($this->config))->execute($typesCollection);

        $contents = @file_get_contents($this->config->getOutputFile());
        @file_put_contents($this->config->getOutputFile(),
            str(LaramixComponent::namespaceToName($contents))
                ->replace(' '.LaramixComponent::NAMESPACE.'.', ' ')
                ->replace('namespace Laramix.Laramix', 'namespace Laramix')
                ->toString().self::hardcoded);

        (new FormatTypeScriptAction($this->config))->execute();

        $this->generateRouteTypes();

        return $typesCollection;
    }

    public function generateRouteTypes()
    {

        $ts = "";

        $routesDirectory = config('laramix.routes_directory');
        $routeTypesPath = config('laramix.route_types_path');
        collect(File::allFiles($routesDirectory))->map(function (\SplFileInfo $file) use (&$ts) {
            $fileName =  $file->getFilenameWithoutExtension();
            $ts .= "declare module './routes/$fileName.tsx' {
    type Props = $fileName.Props
     export default function(props: Props): JSX.Element
 }" . PHP_EOL. PHP_EOL;
        });
      
        $ts .= "export {}";
        File::put($routeTypesPath, $ts);
    }
    
}
