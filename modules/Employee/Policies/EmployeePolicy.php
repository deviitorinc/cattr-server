<?php

namespace Modules\Employee\Policies;

use Modules\Employee\Entities\Employee;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class EmployeePolicy
{
    use HandlesAuthorization;

    public function before(User $user): ?bool
    {
        return $user->isAdmin() ?: null;
    }

    public function viewAny(): bool
    {
        return true;
    }

    public function view(User $user, Employee $model): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, Employee $model): bool
    {
        return $user->isAdmin();
    }

    public function destroy(User $user, Employee $model): bool
    {
        return $user->isAdmin();
    }
}