<?php

declare(strict_types=1);

use App\Models\Company;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

/** Private per-tenant channel. Members of that company may listen. */
Broadcast::channel('company.{companyId}', function ($user, $companyId) {
    return (int) $user->company_id === (int) $companyId;
});

/** Admin-only channel for platform-wide alerts. */
Broadcast::channel('admin', function ($user) {
    return (bool) $user->is_admin;
});
