<?php

namespace Jqhtml\Laravel;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Blade;
use Jqhtml\Laravel\Blade\JqhtmlBladePrecompiler;

class JqhtmlServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Register precompiler for direct syntax: <Component_Name $arg="val" />
        // Transforms uppercase component tags into hydration placeholders
        Blade::precompiler(function (string $string) {
            return JqhtmlBladePrecompiler::compile($string);
        });
    }
}
