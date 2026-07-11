<?php

use App\Exceptions\Kanban\BoardAlreadyExistsException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // statefulApi() removed in auth-bearer-mode-2026 — bearer mode does not
        // need EnsureFrontendRequestsAreStateful because cookies are not issued.
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );

        // Map the kanban board name-uniqueness ValidationException to the
        // typed `BoardAlreadyExistsException` so the API surfaces a stable
        // `code: name_taken` envelope the frontend can branch on. The custom
        // Rule (App\Rules\UniqueActiveBoardName) reports the failure with the
        // message "A board with that name already exists in this project." —
        // that text is the discriminator the renderer keys off.
        $exceptions->render(function (ValidationException $e, Request $request): ?Response {
            if (! $request->is('api/*')) {
                return null;
            }

            $errors = $e->errors();
            $nameError = $errors['name'] ?? null;

            if (! is_array($nameError)) {
                return null;
            }

            foreach ($nameError as $message) {
                if ($message === 'A board with that name already exists in this project.') {
                    // Recover the project id from the route so the response
                    // shape stays consistent with the controller-level path.
                    $project = $request->route('project');
                    $projectId = is_object($project) && method_exists($project, 'getKey')
                        ? (int) $project->getKey()
                        : 0;

                    $name = (string) $request->input('name', '');

                    throw new BoardAlreadyExistsException($name, $projectId);
                }
            }

            return null;
        });
    })->create();
