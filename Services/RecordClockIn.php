<?php

namespace Modules\CattrClockInWebhook\Services;

use App\Models\TimeInterval;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\CattrClockInWebhook\Jobs\SendClockInWebhook;
use Throwable;

class RecordClockIn
{
    private const TABLE = 'cattr_clock_in_webhook_attempts';

    public function handle(TimeInterval $interval): void
    {
        if (!$this->shouldSend($interval)) {
            return;
        }

        try {
            $personName = $interval->user()
                ->withoutGlobalScopes()
                ->value('full_name');

            if (!is_string($personName) || trim($personName) === '') {
                Log::warning('Cattr clock-in webhook skipped because the employee name is empty', [
                    'user_id' => $interval->user_id,
                    'interval_id' => $interval->id,
                ]);

                return;
            }

            $clockInDate = CarbonImmutable::now(
                config('cattr-clock-in-webhook.timezone', 'Asia/Colombo')
            )->toDateString();

            $inserted = DB::table(self::TABLE)->insertOrIgnore([
                'user_id' => $interval->user_id,
                'person_name' => $personName,
                'clock_in_date' => $clockInDate,
                'status' => 'pending',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            if ($inserted !== 1) {
                return;
            }

            $attemptId = DB::table(self::TABLE)
                ->where('user_id', $interval->user_id)
                ->where('clock_in_date', $clockInDate)
                ->value('id');

            if ($attemptId === null) {
                Log::error('Cattr clock-in webhook attempt was inserted but could not be loaded', [
                    'user_id' => $interval->user_id,
                    'clock_in_date' => $clockInDate,
                ]);

                return;
            }

            SendClockInWebhook::dispatch((int) $attemptId, $personName)->afterResponse();
        } catch (Throwable $exception) {
            Log::error('Cattr clock-in webhook could not be scheduled', [
                'user_id' => $interval->user_id,
                'interval_id' => $interval->id,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    private function shouldSend(TimeInterval $interval): bool
    {
        if (!config('cattr-clock-in-webhook.enabled', false)) {
            return false;
        }

        $url = config('cattr-clock-in-webhook.url');
        $token = config('cattr-clock-in-webhook.token');

        if (!is_string($url) || trim($url) === '' || !is_string($token) || trim($token) === '') {
            return false;
        }

        if ($interval->is_manual) {
            return false;
        }

        return auth()->id() === $interval->user_id;
    }
}
