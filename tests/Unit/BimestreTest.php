<?php

use App\Enums\DivisaoLetiva;
use App\Support\Bimestre;
use Illuminate\Support\Carbon;

uses(Tests\TestCase::class);

afterEach(function () {
    Carbon::setTestNow();
});

it('maps civil months to bimestres', function (int $month, int $periodo) {
    Carbon::setTestNow(Carbon::create(2026, $month, 15));

    expect(Bimestre::atual())->toBe($periodo)
        ->and(Bimestre::atual(DivisaoLetiva::Bimestral))->toBe($periodo);
})->with([
    'janeiro' => [1, 1],
    'março' => [3, 1],
    'abril' => [4, 2],
    'junho' => [6, 2],
    'julho' => [7, 3],
    'setembro' => [9, 3],
    'outubro' => [10, 4],
    'dezembro' => [12, 4],
]);

it('maps civil months to trimestres', function (int $month, int $periodo) {
    Carbon::setTestNow(Carbon::create(2026, $month, 15));

    expect(Bimestre::atual(DivisaoLetiva::Trimestral))->toBe($periodo);
})->with([
    'janeiro' => [1, 1],
    'abril' => [4, 1],
    'maio' => [5, 2],
    'agosto' => [8, 2],
    'setembro' => [9, 3],
    'dezembro' => [12, 3],
]);

it('exposes three periodos for trimestral schools', function () {
    expect(Bimestre::numeros(DivisaoLetiva::Trimestral))->toBe([1, 2, 3])
        ->and(Bimestre::quantidade(DivisaoLetiva::Trimestral))->toBe(3)
        ->and(Bimestre::label(2, DivisaoLetiva::Trimestral))->toBe('2º Trimestre')
        ->and(Bimestre::rules(DivisaoLetiva::Trimestral))->toBe(['required', 'integer', 'min:1', 'max:3']);
});
