<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class RouteControllerResolutionTest extends TestCase
{
    public function test_semua_rute_controller_mengarah_ke_kelas_dan_method_yang_ada(): void
    {
        $missing = [];

        foreach (Route::getRoutes() as $route) {
            $action = $route->getActionName();
            if ($action === 'Closure' || ! str_contains($action, '@')) {
                continue;
            }

            [$controller, $method] = explode('@', $action, 2);
            if (! class_exists($controller) || ! method_exists($controller, $method ?: '__invoke')) {
                $missing[] = implode(' ', [$route->methods()[0] ?? 'UNKNOWN', $route->uri(), $action]);
            }
        }

        $this->assertSame([], $missing, 'Rute menunjuk ke controller/method yang hilang: '.implode('; ', $missing));
    }
}
