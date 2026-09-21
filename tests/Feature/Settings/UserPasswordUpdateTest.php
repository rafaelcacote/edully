<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

it('does not mass-assign the password attribute', function () {
    $user = User::factory()->create([
        'password_hash' => Hash::make('password'),
    ]);

    $originalHash = $user->password_hash;

    $user->update([
        'password' => 'new-password',
    ]);

    expect($user->refresh()->password_hash)->toBe($originalHash)
        ->and(Hash::check('password', $user->password_hash))->toBeTrue();
});

it('updates the password hash when assigning the password attribute', function () {
    $user = User::factory()->create([
        'password_hash' => Hash::make('password'),
    ]);

    $user->password = 'new-password';
    $user->save();

    expect(Hash::check('new-password', $user->refresh()->password_hash))->toBeTrue()
        ->and(Hash::check('password', $user->password_hash))->toBeFalse();
});
