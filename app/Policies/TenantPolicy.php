<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class TenantPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->organization_id !== null;
    }

    public function create(User $user): bool
    {
        return $user->organization_id !== null;
    }

    public function view(User $user, Model $record): bool
    {
        return $this->owns($user, $record);
    }

    public function update(User $user, Model $record): bool
    {
        return $this->owns($user, $record);
    }

    public function delete(User $user, Model $record): bool
    {
        return $this->owns($user, $record);
    }

    public function deleteAny(User $user): bool
    {
        return $user->organization_id !== null;
    }

    public function attach(User $user, Model $record): bool
    {
        return $this->owns($user, $record);
    }

    public function detach(User $user, Model $record): bool
    {
        return $this->owns($user, $record);
    }

    public function detachAny(User $user, Model $record): bool
    {
        return $this->owns($user, $record);
    }

    private function owns(User $user, Model $record): bool
    {
        return $user->organization_id !== null
            && $user->organization_id === $record->getAttribute('organization_id');
    }
}
