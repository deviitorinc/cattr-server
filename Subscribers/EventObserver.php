<?php

namespace Modules\CattrClockInWebhook\Subscribers;

use App\Models\TimeInterval;
use Modules\CattrClockInWebhook\Services\RecordClockIn;

class EventObserver
{
    public function intervalCreation(TimeInterval $interval): void
    {
        app(RecordClockIn::class)->handle($interval);
    }

    public function subscribe(): array
    {
        return [
            'event.after.action.intervals.create' => [[__CLASS__, 'intervalCreation']],
        ];
    }
}
