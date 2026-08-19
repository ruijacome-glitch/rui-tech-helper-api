<?php

use App\Enums\UserRole;
use App\Models\User;

test('user role casts to the UserRole enum', function () {
    $user = User::factory()->create(['role' => UserRole::Admin]);

    expect($user->fresh()->role)->toBe(UserRole::Admin);
});

test('user role defaults to cliente when not specified', function () {
    $user = User::factory()->create();

    expect($user->fresh()->role)->toBe(UserRole::Cliente);
});
