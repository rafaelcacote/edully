<?php

use App\Http\Middleware\HandleAppearance;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;

test('guests are redirected from appearance settings', function () {
    $this->get(route('appearance.edit'))
        ->assertRedirect(route('login'));
});

test('handle appearance shares the cookie value with views', function () {
    $request = Request::create('/dummy');
    $request->cookies->set('appearance', 'dark');

    $middleware = new HandleAppearance;
    $middleware->handle($request, fn () => response('ok'));

    expect(View::shared('appearance'))->toBe('dark');
});

test('dark appearance cookie applies the dark class to the document', function () {
    $response = $this->withUnencryptedCookie('appearance', 'dark')
        ->get(route('login'));

    $response->assertOk();
    expect($response->getContent())->toContain('class="dark"');
});

test('light appearance cookie does not apply the dark class to the document', function () {
    $response = $this->withUnencryptedCookie('appearance', 'light')
        ->get(route('login'));

    $response->assertOk();
    expect($response->getContent())->not->toContain('class="dark"');
});
