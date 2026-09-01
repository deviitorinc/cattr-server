<?php

namespace Modules\CattrClockInWebhook\Tests\Feature;

use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Modules\CattrClockInWebhook\Jobs\SendClockInWebhook;
use Tests\Facades\IntervalFactory;
use Tests\Facades\TaskFactory;
use Tests\Facades\UserFactory;
use Tests\TestCase;

class RecordClockInTest extends TestCase
{
    private const URI = 'time-intervals/create';

    private User $user;

    private Task $task;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set([
            'cattr-clock-in-webhook.enabled' => true,
            'cattr-clock-in-webhook.url' => 'https://automata.example.test/webhooks/cattr/clock-in',
            'cattr-clock-in-webhook.token' => 'test-token',
            'cattr-clock-in-webhook.timezone' => 'Asia/Colombo',
        ]);

        Queue::fake();

        $this->user = UserFactory::asAdmin()->withTokens()->create();
        $this->task = TaskFactory::forUser($this->user)->create();
    }

    public function test_first_automatic_interval_schedules_one_webhook(): void
    {
        $response = $this->actingAs($this->user)->postJson(self::URI, $this->intervalData());

        $response->assertOk();
        $this->app->terminate();

        $this->assertDatabaseHas('cattr_clock_in_webhook_attempts', [
            'user_id' => $this->user->id,
            'person_name' => $this->user->full_name,
            'status' => 'pending',
        ]);
        Queue::assertPushed(
            SendClockInWebhook::class,
            fn (SendClockInWebhook $job): bool => $job->personName === $this->user->full_name
        );
    }

    public function test_additional_intervals_on_the_same_day_do_not_schedule_another_webhook(): void
    {
        $first = $this->intervalData();
        $second = $this->intervalData();
        $second['start_at'] = now()->subMinutes(20)->toIso8601String();
        $second['end_at'] = now()->subMinutes(15)->toIso8601String();

        $this->actingAs($this->user)->postJson(self::URI, $first)->assertOk();
        $this->actingAs($this->user)->postJson(self::URI, $second)->assertOk();
        $this->app->terminate();

        self::assertSame(
            1,
            DB::table('cattr_clock_in_webhook_attempts')
                ->where('user_id', $this->user->id)
                ->count()
        );
        Queue::assertPushed(SendClockInWebhook::class, 1);
    }

    public function test_manual_interval_does_not_schedule_a_webhook(): void
    {
        $interval = $this->intervalData();
        $interval['is_manual'] = true;

        $this->actingAs($this->user)->postJson(self::URI, $interval)->assertOk();

        $this->assertDatabaseMissing('cattr_clock_in_webhook_attempts', [
            'user_id' => $this->user->id,
        ]);
        Queue::assertNothingPushed();
    }

    private function intervalData(): array
    {
        return array_merge(IntervalFactory::createRandomModelData(), [
            'task_id' => $this->task->id,
            'user_id' => $this->user->id,
        ]);
    }
}
