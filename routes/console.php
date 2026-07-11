<?php

declare(strict_types=1);

use App\Jobs\PurgeSoftDeletedBoards;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Daily kanban board purge at 03:00. Runs the `PurgeSoftDeletedBoards` job
// (Batch 1.9) which force-deletes any board whose `deleted_at` is older
// than `config('kanban.purge_after_days')` (default 30). Scheduled off-peak
// so the cron hot-path isn't on the request peak. WithoutOverlapping to
// prevent a long-running purge from doubling up if the previous day's run
// is still going.
Schedule::job(new PurgeSoftDeletedBoards)
    ->dailyAt('03:00')
    ->name('kanban:purge-soft-deleted-boards')
    ->withoutOverlapping();
