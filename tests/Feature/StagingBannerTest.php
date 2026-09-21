<?php

test('shows homologation banner when environment is staging', function () {
    $this->app['env'] = 'staging';

    $response = $this->get(route('login'));

    $response->assertOk();
    $response->assertSee('Ambiente de homologação — somente para testes', false);
});

test('shows demo banner when environment is demo', function () {
    $this->app['env'] = 'demo';

    $response = $this->get(route('login'));

    $response->assertOk();
    $response->assertSee('Ambiente de demonstração — dados de exemplo', false);
});

test('does not show homologation banner outside staging', function () {
    $this->app['env'] = 'production';

    $response = $this->get(route('login'));

    $response->assertOk();
    $response->assertDontSee('Ambiente de homologação — somente para testes', false);
    $response->assertDontSee('Ambiente de demonstração — dados de exemplo', false);
});
