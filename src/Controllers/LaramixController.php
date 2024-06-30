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

    public function action(Request $request, Laramix $laramix, string $component, string $action)
    {
        return $laramix->component($component)->handleAction($request, $action);
    }
}
