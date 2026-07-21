<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\Tasks\TaskController;
use Illuminate\Support\Facades\Auth;

arch('TaskController does not bypass the authentication policy chokepoint')
    ->expect(TaskController::class)
    ->not->toUse(Auth::class);
