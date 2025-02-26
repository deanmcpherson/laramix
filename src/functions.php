<?php

namespace Laramix\Laramix;

use Closure;
use Inertia\Inertia;
use Vod\Vod\Types\BaseType;
use Vod\Vod\V;

function action(
    ?Closure $handler = null,
    BaseType|string|null $requestType = null,
    BaseType|string|null $responseType = null,
    ?array $middleware = null
) {
    return new Action(
        handler: $handler,
        requestType: $requestType,
        responseType: $responseType,
        middleware: $middleware,
        
    );
}

function v()
{
    return new V();
}



$props = [];

function flushProps() {
    global $props;
    $result = $props;
    $props = [];
    return $result;
}

$exposed = [];
function flushExposed() {
    global $exposed;
    $result = $exposed;  
    $exposed = [];
    return $result;
}

function expose(...$args) {
    
    global $exposed;
    $exposed = $args;
}

function props(array|Closure|Action $args) {
    global $props;
    if (is_array($args)) {
        $props = fn() => $args;
    } else {
        $props = $args;
    }
}

$name = '';
function name(string $routeName) {
    global $name;
    $name = $routeName;
}

function flushName() {
    global $name;
    $result = $name;
    $name = '';
    return $result;
}

function defer(Closure|Action $args, ?string $group = null) {
    if (!$group) {
        return Inertia::defer(function() use ($args) {
            return $args();
        });
    }
    return Inertia::defer(function() use ($args) {
        return $args();
    }, $group);
}

function lazy(Closure|Action $args) {
    return Inertia::lazy(function() use ($args) {
        return $args();
    });    
}