<?php

namespace Laramix\Laramix\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Laramix\Laramix\Laramix;

class LaramixController extends Controller
{
    public function view(Request $request, Laramix $laramix)
    {
        return $laramix->route($request->route()->getName())->render($request);
    }

    public function action(Request $request, Laramix $laramix)
    {

        $parts = str($request->path())->replace('_laramix/', '')->explode('/');

        assert($parts->count() === 2);
        [$component, $action] = $parts->all();

        return $laramix->component($component)->handleAction($request, $action);
    }
}
