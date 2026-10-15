<?php

namespace Modules\CattrClockInWebhook\Services;

use App\Models\TimeInterval;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\CattrClockInWebhook\Jobs\SendTaskStartWebhook;
use Throwable;

class RecordTaskStart
{
    private const TABLE = 'cattr_task_start_webhook_attempts';

    public function handle(TimeInterval $interval): void
    {
        if (!$this->shouldSend($interval)) {
            return;
        }

        try {
            $trackingDate = CarbonImmutable::now(
                config('cattr-clock-in-webhook.timezone', 'Asia/Colombo')
            )->toDateString();
            if (!$this->isNewTaskForDay($interval, $trackingDate)) {
                return;
            }

            $personName = $interval->user()->withoutGlobalScopes()->value('full_name');
            $taskName = $interval->task()->value('task_name');
            if (!is_string($personName) || trim($personName) === ''
                || !is_string($taskName) || trim($taskName) === '') {
                Log::warning('Cattr task-start webhook skipped because the person or task name is empty', [
                    'interval_id' => $interval->id,
                ]);
                return;
            }

            $inserted = DB::table(self::TABLE)->insertOrIgnore([
                'interval_id' => $interval->id,
                'user_id' => $interval->user_id,
                'task_id' => $interval->task_id,
                'tracking_date' => $trackingDate,
                'person_name' => $personName,
                'task_name' => $taskName,
                'status' => 'pending',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            if ($inserted !== 1) {
                return;
            }

            $attemptId = DB::table(self::TABLE)->where('interval_id', $interval->id)->value('id');
            if ($attemptId === null) {
                Log::error('Cattr task-start webhook attempt could not be loaded', [
                    'interval_id' => $interval->id,
                ]);
                return;
            }

            SendTaskStartWebhook::dispatch((int) $attemptId, (int) $interval->id, $personName, $taskName)
                ->afterResponse();
        } catch (Throwable $exception) {
            Log::error('Cattr task-start webhook could not be scheduled', [
                'interval_id' => $interval->id,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    private function shouldSend(TimeInterval $interval): bool
    {
        if (!config('cattr-clock-in-webhook.task_start_enabled', false)) {
            return false;
        }
        if (!is_string(config('cattr-clock-in-webhook.task_start_url'))
            || trim(config('cattr-clock-in-webhook.task_start_url')) === '') {
            return false;
        }
        if (!is_string(config('cattr-clock-in-webhook.token'))
            || trim(config('cattr-clock-in-webhook.token')) === '') {
            return false;
        }

        return !$interval->is_manual && auth()->id() === $interval->user_id;
    }

    private function isNewTaskForDay(TimeInterval $interval, string $trackingDate): bool
    {
        // Only earlier live events recorded by this hook count; offline-imported
        // intervals never enter this table and cannot alter notification state.
        $previousTaskId = DB::table(self::TABLE)
            ->where('user_id', $interval->user_id)
            ->where('tracking_date', $trackingDate)
            ->where('interval_id', '<', $interval->id)
            ->orderByDesc('interval_id')
            ->value('task_id');

        return $previousTaskId === null || (int) $previousTaskId !== (int) $interval->task_id;
    }
}
