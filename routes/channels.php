<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// Company email channel authorization
Broadcast::channel('company.{companyId}.emails', function ($user, $companyId) {
    return $user->company_id === (int) $companyId;
});
