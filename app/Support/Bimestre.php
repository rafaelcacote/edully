<?php

namespace App\Support;

use App\Enums\DivisaoLetiva;
use App\Models\Student;
use App\Models\Tenant;

class Bimestre
{
    public static function atual(?DivisaoLetiva $divisao = null): int
    {
        return ($divisao ?? DivisaoLetiva::Bimestral)->atual();
    }

    public static function quantidade(?DivisaoLetiva $divisao = null): int
    {
        return ($divisao ?? DivisaoLetiva::Bimestral)->quantidade();
    }

    public static function unidade(?DivisaoLetiva $divisao = null): string
    {
        return ($divisao ?? DivisaoLetiva::Bimestral)->unidade();
    }

    public static function label(int $bimestre, ?DivisaoLetiva $divisao = null): string
    {
        return ($divisao ?? DivisaoLetiva::Bimestral)->rotulo($bimestre);
    }

    /**
     * @return list<int>
     */
    public static function numeros(?DivisaoLetiva $divisao = null): array
    {
        return range(1, self::quantidade($divisao));
    }

    /**
     * @return list<string>
     */
    public static function rules(?DivisaoLetiva $divisao = null, bool $sometimes = false): array
    {
        $rules = ['required', 'integer', 'min:1', 'max:'.self::quantidade($divisao)];

        if ($sometimes) {
            array_unshift($rules, 'sometimes');
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public static function messages(?DivisaoLetiva $divisao = null): array
    {
        $divisao ??= DivisaoLetiva::Bimestral;
        $unidade = $divisao->unidade();
        $max = $divisao->quantidade();

        return [
            'bimestre.required' => "Selecione o {$unidade}.",
            'bimestre.integer' => "O {$unidade} deve ser um número.",
            'bimestre.min' => "O {$unidade} deve ser entre 1 e {$max}.",
            'bimestre.max' => "O {$unidade} deve ser entre 1 e {$max}.",
            'bimestre.in' => "O {$unidade} deve ser um valor entre 1 e {$max}.",
        ];
    }

    public static function fromAuthenticatedUser(): DivisaoLetiva
    {
        $tenant = auth()->user()?->tenants()->first();

        return $tenant instanceof Tenant
            ? $tenant->divisaoLetiva()
            : DivisaoLetiva::Bimestral;
    }

    public static function fromAuthenticatedTeacher(): DivisaoLetiva
    {
        $teacher = auth()->user()?->teacher()->where('ativo', true)->first();

        return $teacher?->tenant instanceof Tenant
            ? $teacher->tenant->divisaoLetiva()
            : DivisaoLetiva::Bimestral;
    }

    public static function fromStudentId(mixed $studentId): DivisaoLetiva
    {
        if (! is_string($studentId) || $studentId === '') {
            return DivisaoLetiva::Bimestral;
        }

        $student = Student::query()->with('tenant')->find($studentId);

        return $student?->tenant instanceof Tenant
            ? $student->tenant->divisaoLetiva()
            : DivisaoLetiva::Bimestral;
    }
}
