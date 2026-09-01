<?php

namespace Modules\CattrClockInWebhook\Tests\Unit;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Modules\CattrClockInWebhook\Jobs\SendClockInWebhook;
use RuntimeException;
use Tests\TestCase;

class SendClockInWebhookTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set([
            'cattr-clock-in-webhook.url' => 'https://automata.example.test/webhooks/cattr/clock-in',
            'cattr-clock-in-webhook.token' => 'test-token',
            'cattr-clock-in-webhook.connect_timeout' => 2,
            'cattr-clock-in-webhook.timeout' => 5,
        ]);
    }

    public function test_successful_delivery_is_recorded(): void
    {
        Http::fake(['*' => Http::response(['duplicate' => false], 200)]);

        $attemptId = $this->createAttempt();
        $job = new SendClockInWebhook($attemptId, 'Gavin Hemsada');

        $job->handle();

        Http::assertSent(static function (Request $request): bool {
            return $request->url() === 'https://automata.example.test/webhooks/cattr/clock-in'
                && $request->hasHeader('Authorization', 'Bearer test-token')
                && $request['personName'] === 'Gavin Hemsada';
        });
        $this->assertDatabaseHas('cattr_clock_in_webhook_attempts', [
            'id' => $attemptId,
            'status' => 'sent',
            'response_status' => 200,
        ]);
        self::assertSame(1, $job->tries);
    }

    public function test_unsuccessful_response_is_recorded_without_throwing(): void
    {
        Http::fake(['*' => Http::response(['message' => 'Unauthorized'], 401)]);

        $attemptId = $this->createAttempt();

        (new SendClockInWebhook($attemptId, 'Gavin Hemsada'))->handle();

        $this->assertDatabaseHas('cattr_clock_in_webhook_attempts', [
            'id' => $attemptId,
            'status' => 'failed',
            'response_status' => 401,
        ]);
    }

    public function test_ambiguous_exception_is_recorded_as_unknown_without_throwing(): void
    {
        Http::fake(static function (): never {
            throw new RuntimeException('Simulated connection failure');
        });

        $attemptId = $this->createAttempt();

        (new SendClockInWebhook($attemptId, 'Gavin Hemsada'))->handle();

        $this->assertDatabaseHas('cattr_clock_in_webhook_attempts', [
            'id' => $attemptId,
            'status' => 'unknown',
        ]);
    }

    private function createAttempt(): int
    {
        return (int) DB::table('cattr_clock_in_webhook_attempts')->insertGetId([
            'user_id' => 123,
            'person_name' => 'Gavin Hemsada',
            'clock_in_date' => now('Asia/Colombo')->toDateString(),
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
