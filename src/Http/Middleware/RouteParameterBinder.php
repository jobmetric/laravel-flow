<?php

namespace JobMetric\Flow\Http\Middleware;

use Closure;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Http\Request;
use ReflectionClass;
use ReflectionException;
use Str;

class RouteParameterBinder
{
    public function handle(Request $request, Closure $next)
    {
        foreach ($request->route()->parameters() as $parameter => $value) {
            $value = $this->getValue($parameter, $value);

            $request->route()->setParameter($parameter, $value);
        }


        return $next($request);
    }

    private function getValue($parameter, $value)
    {
        $class_name = '\\JobMetric\\Flow\\Models\\' . Str::studly($parameter);

        if (class_exists($class_name)) {
            if (in_array(SoftDeletes::class, class_uses($class_name))) {
                return $class_name::withTrashed()->find($value);
            } else {
                return $class_name::query()->find($value);
            }
        }

        return $value;
    }
}
