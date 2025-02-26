<?php

namespace Laramix\Laramix\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Laramix\Laramix\Laramix;

use function Vod\Vod\v;

class LaramixController extends Controller
{
    public function view(Request $request, Laramix $laramix)
    {
        return $laramix->route($request->route()->laramixRoute())->render($request);
    }

    public function action(Request $request, Laramix $laramix)
    {
        $data = v()
            ->object([
                '_component' => v()->string(),
                '_action' => v()->string(),
                '_args' => v()->any()->array()->optional(),
            ])->parse($request->all());

        $component = $data['_component'];
        $action = $data['_action'];
        $args = $data['_args'] ?? [];

        return $laramix->component($component)->handleAction($request, $action, $args);

    }
}
