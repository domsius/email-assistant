<?php

namespace App\Policies;

use App\Models\EmailMessage;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class EmailMessagePolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view the email message.
     */
    public function view(User $user, EmailMessage $emailMessage): bool
    {
        return $emailMessage->emailAccount->company_id === $user->company_id;
    }

    /**
     * Determine whether the user can update the email message.
     */
    public function update(User $user, EmailMessage $emailMessage): bool
    {
        return $emailMessage->emailAccount->company_id === $user->company_id;
    }

    /**
     * Determine whether the user can delete the email message.
     */
    public function delete(User $user, EmailMessage $emailMessage): bool
    {
        return $emailMessage->emailAccount->company_id === $user->company_id;
    }

    /**
     * Determine whether the user can restore the email message.
     */
    public function restore(User $user, EmailMessage $emailMessage): bool
    {
        return $emailMessage->emailAccount->company_id === $user->company_id;
    }
}
