<?php

use App\Http\Middleware\HandleInertiaRequests;
use App\Models\User;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

it('shares user permissions with inertia', function () {
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $permission = Permission::findOrCreate('escola.disciplinas.visualizar', 'web');
    $role = Role::findOrCreate('Professor', 'web');
    $role->givePermissionTo($permission);

    $user = User::factory()->create();
    $user->assignRole($role);

    $request = Request::create('/dummy');
    $request->setLaravelSession($this->app['session']->driver('array'));
    $request->session()->start();
    $request->setUserResolver(static fn () => $user);

    $middleware = new HandleInertiaRequests;
    $shared = $middleware->share($request);

    expect($shared['auth']['user']['permissions'])
        ->toBeArray()
        ->toContain('escola.disciplinas.visualizar');
});
