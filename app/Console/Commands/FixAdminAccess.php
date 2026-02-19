<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Spatie\Permission\Models\Role;

class FixAdminAccess extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'users:fix-admin-access {--email=admin@myschool.local : Email do usuário administrador}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Garante que o usuário administrador tem acesso correto ao sistema';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $email = $this->option('email');
        $user = User::where('email', strtolower($email))->first();

        if (! $user) {
            $this->error("Usuário com email '{$email}' não encontrado.");

            return self::FAILURE;
        }

        $this->info("Corrigindo acesso para: {$user->nome_completo} ({$user->email})");
        $this->newLine();

        // Carregar roles
        $user->load('roles');
        $roleNames = $user->roles->pluck('name')->toArray();

        // Verificar se já tem a role Administrador Geral
        $adminGeralRole = Role::where('name', 'Administrador Geral')->where('guard_name', 'web')->first();

        if (! $adminGeralRole) {
            $this->error('Role "Administrador Geral" não encontrada. Execute: php artisan db:seed --class=PermissionsAndRolesSeeder');

            return self::FAILURE;
        }

        if (! $user->hasRole('Administrador Geral')) {
            $this->warn('  ⚠️  Usuário não tem a role "Administrador Geral"');
            $this->info('  ✅ Atribuindo role "Administrador Geral"...');
            $user->assignRole('Administrador Geral');
            $this->info('  ✅ Role atribuída com sucesso!');
        } else {
            $this->info('  ✅ Usuário já tem a role "Administrador Geral"');
        }

        // Verificar se está ativo
        if (! $user->ativo) {
            $this->warn('  ⚠️  Usuário está INATIVO');
            $this->info('  ✅ Ativando usuário...');
            $user->update(['ativo' => true]);
            $this->info('  ✅ Usuário ativado!');
        } else {
            $this->info('  ✅ Usuário está ativo');
        }

        // Limpar cache
        $this->newLine();
        $this->info('  🧹 Limpando cache...');
        $this->call('cache:clear');
        $this->call('permission:cache-reset');
        $this->info('  ✅ Cache limpo!');

        // Verificar novamente
        $user->refresh();
        $user->load('roles');
        $roleNames = $user->roles->pluck('name')->toArray();
        $isAdminGeral = $user->hasRole('Administrador Geral') || in_array('Administrador Geral', $roleNames);

        $this->newLine();
        if ($isAdminGeral && $user->ativo) {
            $this->info('✅ Usuário administrador configurado corretamente!');
            $this->newLine();
            $this->info('💡 Se ainda não conseguir acessar, faça logout e login novamente.');
        } else {
            $this->error('❌ Ainda há problemas com o acesso do usuário.');
        }

        return self::SUCCESS;
    }
}
