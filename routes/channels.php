<?php

use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Here you may register all of the event broadcasting channels that your
| application supports. The given channel authorization callbacks are
| used to check if an authenticated user can listen to the channel.
|
*/

// Company-wide email channel
Broadcast::channel('company.{companyId}.emails', function ($user, $companyId) {
    return (int) $user->company_id === (int) $companyId;
});