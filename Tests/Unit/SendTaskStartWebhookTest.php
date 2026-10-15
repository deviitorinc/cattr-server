<?php

namespace Modules\CattrClockInWebhook\Tests\Unit;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Modules\CattrClockInWebhook\Jobs\SendTaskStartWebhook;
use RuntimeException;
use Tests\TestCase;

class SendTaskStartWebhookTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config()->set([
            'cattr-clock-in-webhook.task_start_url' => 'https://automata.example.test/webhooks/cattr/task-start',
            'cattr-clock-in-webhook.token' => 'test-token',
            'cattr-clock-in-webhook.connect_timeout' => 2,
            'cattr-clock-in-webhook.timeout' => 5,
        ]);
    }

    public function test_successful_delivery_uses_a_bounded_fixed_payload(): void
    {
        Http::fake(['*' => Http::response(['ok' => true], 200)]);
        (new SendTaskStartWebhook($this->createAttempt(), 123, 'Pasidu', 'Task Y'))->handle();

        Http::assertSent(static function (Request $request): bool {
            return $request->url() === 'https://automata.example.test/webhooks/cattr/task-start'
                && $request->hasHeader('Authorization', 'Bearer test-token')
                && $request['intervalId'] === 123
                && $request['personName'] === 'Pasidu'
                && $request['taskName'] === 'Task Y';
        });
        $this->assertDatabaseHas('cattr_task_start_webhook_attempts', [
            'interval_id' => 123,
            'status' => 'sent',
            'response_status' => 200,
        ]);
    }

    public function test_ambiguous_exception_is_not_retried(): void
    {
        Http::fake(static function (): never {
            throw new RuntimeException('Simulated timeout');
        });
        $job = new SendTaskStartWebhook($this->createAttempt(), 123, 'Pasidu', 'Task Y');
        $job->handle();
        $this->assertDatabaseHas('cattr_task_start_webhook_attempts', [
            'interval_id' => 123,
            'status' => 'unknown',
        ]);
        self::assertSame(1, $job->tries);
    }

    private function createAttempt(): int
    {
        return (int) DB::table('cattr_task_start_webhook_attempts')->insertGetId([
            'interval_id' => 123,
            'user_id' => 1,
            'task_id' => 2,
            'tracking_date' => now('Asia/Colombo')->toDateString(),
            'person_name' => 'Pasidu',
            'task_name' => 'Task Y',
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
