<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class QuickSetup extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:quick-setup';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Configura rapidamente o sistema: seeders + usuário admin + permissões';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🚀 Iniciando configuração rápida do sistema...');
        $this->newLine();

        // 1. Executar seeders
        $this->info('📝 Passo 1: Executando seeders de permissões...');
        $this->call('db:seed', ['--class' => 'PermissionsAndRolesSeeder']);
        $this->info('✅ Seeders executados!');
        $this->newLine();

        // 2. Criar/atualizar usuário admin
        $this->info('👤 Passo 2: Configurando usuário administrador...');
        $this->call('app:create-admin-user');
        $this->newLine();

        // 3. Configurar permissões
        $this->info('🔐 Passo 3: Configurando permissões e roles...');
        $this->call('app:setup-permissions');
        $this->newLine();

        // 4. Limpar cache
        $this->info('🧹 Passo 4: Limpando cache...');
        $this->call('cache:clear');
        $this->call('config:clear');
        $this->call('permission:cache-reset');
        $this->info('✅ Cache limpo!');
        $this->newLine();

        $this->info('🎉 ═══════════════════════════════════════════════════════');
        $this->info('🎉 CONFIGURAÇÃO CONCLUÍDA COM SUCESSO!');
        $this->info('🎉 ═══════════════════════════════════════════════════════');
        $this->newLine();
        $this->info('📋 Credenciais de acesso:');
        $this->info('   CPF: 74527436287');
        $this->info('   Senha: 12031986');
        $this->newLine();
        $this->info('💡 Dica: Execute este comando sempre após rodar migrations');
        $this->info('   para recuperar suas permissões.');

        return self::SUCCESS;
    }
}
