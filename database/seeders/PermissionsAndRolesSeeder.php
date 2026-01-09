<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class PermissionsAndRolesSeeder extends Seeder
{
    public function run(): void
    {
        // Guard padrão usado pela aplicação (Fortify/Inertia)
        $guard = 'web';

        // Permissões de Escolas (Tenants)
        $escolasPermissions = [
            'escolas.visualizar',
            'escolas.criar',
            'escolas.editar',
            'escolas.excluir',
        ];

        // Permissões de Usuários
        $usuariosPermissions = [
            'usuarios.visualizar',
            'usuarios.criar',
            'usuarios.editar',
            'usuarios.excluir',
        ];

        // Permissões de Roles
        $rolesPermissions = [
            'roles.visualizar',
            'roles.criar',
            'roles.editar',
            'roles.excluir',
        ];

        // Permissões de Permissões
        $permissionsPermissions = [
            'permissoes.visualizar',
            'permissoes.criar',
            'permissoes.editar',
            'permissoes.excluir',
        ];

        // Permissões de Assinaturas
        $assinaturasPermissions = [
            'assinaturas.visualizar',
        ];

        // Permissões de Planos
        $planosPermissions = [
            'planos.visualizar',
            'planos.criar',
            'planos.editar',
        ];

        // Permissões de Logs do Sistema
        $logsPermissions = [
            'logs.visualizar',
        ];

        // Permissões de Escola (Administrador Escola)
        $escolaPermissions = [
            'escola.perfil.visualizar',
            'escola.alunos.visualizar',
            'escola.alunos.criar',
            'escola.alunos.editar',
            'escola.alunos.excluir',
            'escola.responsaveis.visualizar',
            'escola.responsaveis.criar',
            'escola.responsaveis.editar',
            'escola.responsaveis.excluir',
            'escola.professores.visualizar',
            'escola.professores.criar',
            'escola.professores.editar',
            'escola.professores.excluir',
            'escola.turmas.visualizar',
            'escola.turmas.criar',
            'escola.turmas.editar',
            'escola.turmas.excluir',
            'escola.exercicios.visualizar',
            'escola.exercicios.criar',
            'escola.exercicios.editar',
            'escola.exercicios.excluir',
            'escola.provas.visualizar',
            'escola.provas.criar',
            'escola.provas.editar',
            'escola.provas.excluir',
            'escola.disciplinas.visualizar',
            'escola.disciplinas.criar',
            'escola.disciplinas.editar',
            'escola.disciplinas.excluir',
        ];

        // Permissões para Professores
        $professorPermissions = [
            'escola.exercicios.visualizar',
            'escola.exercicios.criar',
            'escola.exercicios.editar',
            'escola.exercicios.excluir',
            'escola.provas.visualizar',
            'escola.provas.criar',
            'escola.provas.editar',
            'escola.provas.excluir',
            'escola.disciplinas.visualizar',
        ];

        // Todas as permissões
        $allPermissions = array_merge(
            $escolasPermissions,
            $usuariosPermissions,
            $rolesPermissions,
            $permissionsPermissions,
            $assinaturasPermissions,
            $planosPermissions,
            $logsPermissions,
            $escolaPermissions
        );

        // Criar todas as permissões
        foreach ($allPermissions as $permissionName) {
            Permission::findOrCreate($permissionName, $guard);
        }

        // Perfil Administrador Geral
        $role = Role::findOrCreate('Administrador Geral', $guard);
        $role->syncPermissions($allPermissions);

        // Perfil Administrador Escola
        $roleEscola = Role::findOrCreate('Administrador Escola', $guard);
        $roleEscola->syncPermissions($escolaPermissions);

        // Perfil Professor
        $roleProfessor = Role::findOrCreate('Professor', $guard);
        $roleProfessor->syncPermissions($professorPermissions);
    }
}
