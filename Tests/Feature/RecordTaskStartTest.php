<?php

namespace Modules\CattrClockInWebhook\Tests\Feature;

use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Modules\CattrClockInWebhook\Jobs\SendTaskStartWebhook;
use Tests\Facades\IntervalFactory;
use Tests\Facades\TaskFactory;
use Tests\Facades\UserFactory;
use Tests\TestCase;

class RecordTaskStartTest extends TestCase
{
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-15T12:00:00+05:30');
        config()->set([
            'cattr-clock-in-webhook.enabled' => false,
            'cattr-clock-in-webhook.task_start_enabled' => true,
            'cattr-clock-in-webhook.task_start_url' => 'https://automata.example.test/webhooks/cattr/task-start',
            'cattr-clock-in-webhook.token' => 'test-token',
            'cattr-clock-in-webhook.timezone' => 'Asia/Colombo',
        ]);
        Queue::fake();
        $this->user = UserFactory::asAdmin()->withTokens()->create();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_first_task_and_each_switch_are_sent_but_repeated_intervals_are_not(): void
    {
        $firstTask = TaskFactory::forUser($this->user)->create();
        $secondTask = TaskFactory::forUser($this->user)->create();

        $this->createInterval($firstTask->id, 30);
        $this->createInterval($firstTask->id, 20);
        $this->createInterval($secondTask->id, 10);
        $this->createInterval($firstTask->id, 5);
        $this->app->terminate();

        self::assertSame(3, DB::table('cattr_task_start_webhook_attempts')->count());
        Queue::assertPushed(SendTaskStartWebhook::class, 3);
        Queue::assertPushed(SendTaskStartWebhook::class,
            fn (SendTaskStartWebhook $job): bool => $job->taskName === $secondTask->task_name);
    }

    public function test_manual_interval_is_ignored(): void
    {
        $task = TaskFactory::forUser($this->user)->create();
        $this->createInterval($task->id, 10, true);
        $this->app->terminate();
        self::assertSame(0, DB::table('cattr_task_start_webhook_attempts')->count());
        Queue::assertNotPushed(SendTaskStartWebhook::class);
    }

    public function test_same_task_is_announced_again_on_the_next_colombo_day(): void
    {
        $task = TaskFactory::forUser($this->user)->create();
        $this->createInterval($task->id, 10);
        Carbon::setTestNow('2026-10-16T12:00:00+05:30');
        $this->createInterval($task->id, 10);
        $this->app->terminate();

        self::assertSame(2, DB::table('cattr_task_start_webhook_attempts')->count());
        Queue::assertPushed(SendTaskStartWebhook::class, 2);
    }

    private function createInterval(int $taskId, int $minutesAgo, bool $manual = false): void
    {
        $data = array_merge(IntervalFactory::createRandomModelData(), [
            'task_id' => $taskId,
            'user_id' => $this->user->id,
            'start_at' => now()->subMinutes($minutesAgo)->toIso8601String(),
            'end_at' => now()->subMinutes($minutesAgo - 1)->toIso8601String(),
            'is_manual' => $manual,
        ]);
        $this->actingAs($this->user)->postJson('time-intervals/create', $data)->assertOk();
    }
}
