<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class DiagnoseUserAccess extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'users:diagnose-access {--email= : Email do usuário específico} {--cpf= : CPF do usuário específico}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Diagnostica problemas de acesso de usuários ao sistema';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $email = $this->option('email');
        $cpf = $this->option('cpf');

        if ($email) {
            $users = User::where('email', strtolower($email))->get();
        } elseif ($cpf) {
            $cpfClean = preg_replace('/[^0-9]/', '', $cpf);
            $users = User::where('cpf', $cpfClean)->get();
        } else {
            $users = User::all();
        }

        if ($users->isEmpty()) {
            $this->error('Nenhum usuário encontrado.');

            return self::FAILURE;
        }

        $this->info("Analisando {$users->count()} usuário(s)...\n");

        $issues = [];

        foreach ($users as $user) {
            $userIssues = [];

            // Verificar se está ativo
            if (! $user->ativo) {
                $userIssues[] = '❌ Usuário está INATIVO (ativo = false)';
            }

            // Verificar se foi soft deleted
            if ($user->trashed()) {
                $userIssues[] = '❌ Usuário foi DELETADO (soft delete)';
            }

            // Carregar relacionamentos
            $user->load('roles', 'tenants');

            // Verificar se tem roles
            if ($user->roles->isEmpty()) {
                $userIssues[] = '⚠️  Usuário não possui ROLES atribuídas';
            } else {
                $roleNames = $user->roles->pluck('name')->toArray();
                $isAdminGeral = $user->hasRole('Administrador Geral') || in_array('Administrador Geral', $roleNames);

                if (! $isAdminGeral) {
                    // Verificar se tem tenants
                    if ($user->tenants->isEmpty()) {
                        $userIssues[] = '❌ Usuário não possui TENANTS (escolas) vinculados';
                    } else {
                        $this->line("  ✓ Tem {$user->tenants->count()} tenant(s): ".$user->tenants->pluck('nome')->join(', '));
                    }
                } else {
                    $this->line('  ✓ É Administrador Geral (não precisa de tenants)');
                }
            }

            if (! empty($userIssues)) {
                $issues[$user->id] = [
                    'user' => $user,
                    'issues' => $userIssues,
                ];
            }
        }

        if (empty($issues)) {
            $this->info('✅ Todos os usuários verificados têm acesso adequado ao sistema.');

            return self::SUCCESS;
        }

        $this->warn("\n⚠️  Problemas encontrados:\n");

        foreach ($issues as $issue) {
            $user = $issue['user'];
            $this->line("Usuário: {$user->nome_completo} ({$user->email})");
            $this->line("CPF: {$user->cpf}");
            $this->line("ID: {$user->id}");

            foreach ($issue['issues'] as $userIssue) {
                $this->line("  {$userIssue}");
            }

            $this->newLine();
        }

        $this->info('💡 Dicas para corrigir:');
        $this->line('  - Para ativar um usuário: UPDATE shared.usuarios SET ativo = true WHERE id = \'...\'');
        $this->line('  - Para restaurar um usuário deletado: UPDATE shared.usuarios SET deleted_at = NULL WHERE id = \'...\'');
        $this->line('  - Para atribuir roles: php artisan users:assign-default-roles');
        $this->line('  - Para vincular tenants: Use a interface de administração');

        return self::SUCCESS;
    }
}
