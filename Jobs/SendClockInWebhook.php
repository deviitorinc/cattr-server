<?php

namespace Modules\CattrClockInWebhook\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class SendClockInWebhook implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public int $tries = 1;

    public int $timeout = 15;

    public bool $failOnTimeout = true;

    public function __construct(
        public readonly int $attemptId,
        public readonly string $personName,
    ) {
    }

    public function handle(): void
    {
        try {
            $response = Http::acceptJson()
                ->asJson()
                ->withToken((string) config('cattr-clock-in-webhook.token'))
                ->connectTimeout((int) config('cattr-clock-in-webhook.connect_timeout', 2))
                ->timeout((int) config('cattr-clock-in-webhook.timeout', 5))
                ->post((string) config('cattr-clock-in-webhook.url'), [
                    'personName' => $this->personName,
                ]);

            DB::table('cattr_clock_in_webhook_attempts')
                ->where('id', $this->attemptId)
                ->update([
                    'status' => $response->successful() ? 'sent' : 'failed',
                    'response_status' => $response->status(),
                    'response_body' => Str::limit($response->body(), 1000, ''),
                    'attempted_at' => now(),
                    'updated_at' => now(),
                ]);

            if (!$response->successful()) {
                Log::warning('Cattr clock-in webhook returned an unsuccessful response', [
                    'attempt_id' => $this->attemptId,
                    'response_status' => $response->status(),
                ]);
            }
        } catch (Throwable $exception) {
            DB::table('cattr_clock_in_webhook_attempts')
                ->where('id', $this->attemptId)
                ->update([
                    'status' => 'unknown',
                    'error' => Str::limit($exception->getMessage(), 1000, ''),
                    'attempted_at' => now(),
                    'updated_at' => now(),
                ]);

            Log::error('Cattr clock-in webhook outcome is unknown; it will not be retried automatically', [
                'attempt_id' => $this->attemptId,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
