<?php

namespace Modules\Employee\Traits;

use Modules\Employee\Entities\Employee;
use Illuminate\Database\Eloquent\Relations\HasOne;

trait HasEmployee
{
    /**
     * Get the employee associated with the user.
     */
    public function employee(): HasOne
    {
        return $this->hasOne(Employee::class, 'user_id');
    }

    /**
     * Check if the user has employee information.
     */
    public function hasEmployee(): bool
    {
        return $this->employee()->exists();
    }

    /**
     * Get the employee ID if available.
     */
    public function getEmployeeIdAttribute(): ?string
    {
        return $this->employee?->employee_id;
    }

    /**
     * Get the date of joining if available.
     */
    public function getDateOfJoinedAttribute(): ?string
    {
        return $this->employee?->date_of_joined?->format('Y-m-d');
    }
}