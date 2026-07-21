<?php

declare(strict_types=1);

use Illuminate\Routing\Route as RouteDefinition;

it('registers the task lifecycle routes beneath a project', function (): void {
    // Filter by route NAME with a regex anchored to the six task lifecycle
    // verbs. Kanban chain routes (`api.v1.projects.tasks.kanban.*`) also
    // share the `api.v1.projects.tasks.` prefix, so a `str_starts_with`
    // filter would over-match.
    $routes = collect(app('router')->getRoutes()->getRoutes())
        ->filter(fn (RouteDefinition $route): bool => (bool) preg_match(
            '/^api\.v1\.projects\.tasks\.(index|store|show|update|archive|restore)$/',
            (string) $route->getName(),
        ));

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
