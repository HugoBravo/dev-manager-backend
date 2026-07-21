<?php

declare(strict_types=1);

use Illuminate\Routing\Route as RouteDefinition;

it('registers the task lifecycle routes beneath a project', function (): void {
    $routes = collect(app('router')->getRoutes()->getRoutes())
        ->filter(fn (RouteDefinition $route): bool => str_contains($route->uri(), 'projects/{project}/tasks'));

    expect($routes)->toHaveCount(6);

    expect($routes->mapWithKeys(fn (RouteDefinition $route): array => [
        $route->getName() => $route->methods(),
    ])->all())->toMatchArray([
        'api.v1.projects.tasks.index' => ['GET', 'HEAD'],
        'api.v1.projects.tasks.store' => ['POST'],
        'api.v1.projects.tasks.show' => ['GET', 'HEAD'],
        'api.v1.projects.tasks.update' => ['PATCH'],
        'api.v1.projects.tasks.archive' => ['POST'],
        'api.v1.projects.tasks.restore' => ['POST'],
    ]);
});

it('generates the nested task route URLs without changing the auth prefix', function (): void {
    expect(route('api.v1.projects.tasks.index', ['project' => 42]))
        ->toBe(url('/api/v1/projects/42/tasks'));
});
