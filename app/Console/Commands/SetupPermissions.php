<?php

namespace App\Console\Commands;

use App\Models\User;
use Database\Seeders\PermissionsAndRolesSeeder;
use Illuminate\Console\Command;
use Spatie\Permission\Models\Role;

class SetupPermissions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:setup-permissions {--fresh : Recriar todas as permissões e roles}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Configura permissões e roles no sistema e garante que você tenha acesso';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🔧 Configurando permissões e roles...');
        $this->newLine();

        if ($this->option('fresh')) {
            $this->warn('⚠️  Modo --fresh: Limpando cache de permissões...');
            \Artisan::call('permission:cache-reset');
        }

        // Executar o seeder de permissões
        $this->info('📝 Criando/atualizando permissões e roles...');
        $seeder = new PermissionsAndRolesSeeder;
        $seeder->run();
        $this->info('✅ Permissões e roles configurados!');
        $this->newLine();

        // Listar roles criados
        $roles = Role::where('guard_name', 'web')->get();
        $this->info('📋 Roles disponíveis:');
        foreach ($roles as $role) {
            $permissionsCount = $role->permissions()->count();
            $this->line("   • {$role->name} ({$permissionsCount} permissões)");
        }
        $this->newLine();

        // Verificar se você tem uma role atribuída
        $this->info('👤 Verificando seu acesso...');

        // Buscar usuário admin pelo CPF configurado
        $adminUser = User::where('cpf', '74527436287')->first();

        if ($adminUser) {
            $currentRoles = $adminUser->roles->pluck('name')->toArray();

            if (empty($currentRoles)) {
                $this->warn('   Você não possui nenhuma role atribuída!');
                $this->info("   Atribuindo role 'Administrador Geral'...");
                $adminUser->assignRole('Administrador Geral');
                $this->info("   ✅ Role 'Administrador Geral' atribuída com sucesso!");
            } else {
                $this->info('   ✅ Você possui as seguintes roles: '.implode(', ', $currentRoles));

                // Garantir que tem Administrador Geral
                if (! in_array('Administrador Geral', $currentRoles)) {
                    $this->info("   Adicionando role 'Administrador Geral'...");
                    $adminUser->assignRole('Administrador Geral');
                    $this->info("   ✅ Role 'Administrador Geral' atribuída!");
                }
            }
        } else {
            $this->warn('   ⚠️  Usuário admin não encontrado (CPF: 74527436287)');
            $this->info('   Execute: php artisan app:create-admin-user');
        }

        $this->newLine();
        $this->info('🎉 Configuração concluída!');
        $this->info('   Você pode fazer login com:');
        $this->info('   CPF: 74527436287');
        $this->info('   Senha: 12031986');

        return self::SUCCESS;
    }
}
