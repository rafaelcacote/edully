<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $driver = DB::connection('shared')->getDriverName();

        if ($driver === 'sqlite') {
            Schema::connection('shared')->table('mensagens', function ($table) {
                $table->string('tipo')->nullable()->after('conteudo');
                $table->string('prioridade')->nullable()->after('tipo');
                $table->string('anexo_url', 2048)->nullable()->after('prioridade');
                $table->uuid('aluno_id')->nullable()->after('remetente_id');
                $table->uuid('turma_id')->nullable()->after('aluno_id');
            });

            return;
        }

        // Postgres: adicionar campos no schema `escola`
        // Primeiro, verificar se o enum existe e criar se necessário
        DB::connection('shared')->statement("
            DO \$\$
            BEGIN
                IF NOT EXISTS (SELECT 1 FROM pg_type WHERE typname = 'tipo_mensagem') THEN
                    CREATE TYPE tipo_mensagem AS ENUM ('outro', 'informativo', 'atencao', 'aviso', 'lembrete');
                ELSE
                    -- Se o enum já existe, tentar adicionar valores que não existem
                    -- Nota: Só podemos adicionar valores ao final do enum no PostgreSQL
                    -- Verificamos se o valor existe antes de adicionar
                    IF NOT EXISTS (
                        SELECT 1 FROM pg_enum 
                        WHERE enumlabel = 'outro' 
                        AND enumtypid = (SELECT oid FROM pg_type WHERE typname = 'tipo_mensagem')
                    ) THEN
                        ALTER TYPE tipo_mensagem ADD VALUE 'outro';
                    END IF;
                    
                    IF NOT EXISTS (
                        SELECT 1 FROM pg_enum 
                        WHERE enumlabel = 'informativo' 
                        AND enumtypid = (SELECT oid FROM pg_type WHERE typname = 'tipo_mensagem')
                    ) THEN
                        ALTER TYPE tipo_mensagem ADD VALUE 'informativo';
                    END IF;
                    
                    IF NOT EXISTS (
                        SELECT 1 FROM pg_enum 
                        WHERE enumlabel = 'atencao' 
                        AND enumtypid = (SELECT oid FROM pg_type WHERE typname = 'tipo_mensagem')
                    ) THEN
                        ALTER TYPE tipo_mensagem ADD VALUE 'atencao';
                    END IF;
                    
                    IF NOT EXISTS (
                        SELECT 1 FROM pg_enum 
                        WHERE enumlabel = 'aviso' 
                        AND enumtypid = (SELECT oid FROM pg_type WHERE typname = 'tipo_mensagem')
                    ) THEN
                        ALTER TYPE tipo_mensagem ADD VALUE 'aviso';
                    END IF;
                    
                    IF NOT EXISTS (
                        SELECT 1 FROM pg_enum 
                        WHERE enumlabel = 'lembrete' 
                        AND enumtypid = (SELECT oid FROM pg_type WHERE typname = 'tipo_mensagem')
                    ) THEN
                        ALTER TYPE tipo_mensagem ADD VALUE 'lembrete';
                    END IF;
                END IF;
            END
            \$\$;
        ");

        // Verificar se o campo aluno_id já existe
        $alunoIdExists = DB::connection('shared')->select("
            SELECT column_name 
            FROM information_schema.columns 
            WHERE table_schema = 'escola' 
            AND table_name = 'mensagens' 
            AND column_name = 'aluno_id'
        ");

        if (empty($alunoIdExists)) {
            DB::connection('shared')->statement('
                ALTER TABLE escola.mensagens 
                ADD COLUMN aluno_id UUID
            ');
        }

        // Verificar se o campo tipo já existe
        $tipoExists = DB::connection('shared')->select("
            SELECT column_name 
            FROM information_schema.columns 
            WHERE table_schema = 'escola' 
            AND table_name = 'mensagens' 
            AND column_name = 'tipo'
        ");

        if (empty($tipoExists)) {
            DB::connection('shared')->statement('
                ALTER TABLE escola.mensagens 
                ADD COLUMN tipo tipo_mensagem
            ');
        }

        // Verificar se o campo prioridade já existe
        $prioridadeExists = DB::connection('shared')->select("
            SELECT column_name 
            FROM information_schema.columns 
            WHERE table_schema = 'escola' 
            AND table_name = 'mensagens' 
            AND column_name = 'prioridade'
        ");

        if (empty($prioridadeExists)) {
            DB::connection('shared')->statement("
                DO \$\$
                BEGIN
                    IF NOT EXISTS (SELECT 1 FROM pg_type WHERE typname = 'prioridade_mensagem') THEN
                        CREATE TYPE prioridade_mensagem AS ENUM ('normal', 'alta', 'media');
                    END IF;
                END
                \$\$;
            ");

            DB::connection('shared')->statement('
                ALTER TABLE escola.mensagens 
                ADD COLUMN prioridade prioridade_mensagem
            ');
        }

        // Verificar se o campo anexo_url já existe
        $anexoUrlExists = DB::connection('shared')->select("
            SELECT column_name 
            FROM information_schema.columns 
            WHERE table_schema = 'escola' 
            AND table_name = 'mensagens' 
            AND column_name = 'anexo_url'
        ");

        if (empty($anexoUrlExists)) {
            DB::connection('shared')->statement('
                ALTER TABLE escola.mensagens 
                ADD COLUMN anexo_url VARCHAR(2048)
            ');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $driver = DB::connection('shared')->getDriverName();

        if ($driver === 'sqlite') {
            Schema::connection('shared')->table('mensagens', function ($table) {
                $table->dropColumn(['tipo', 'prioridade', 'anexo_url', 'aluno_id', 'turma_id']);
            });

            return;
        }

        // Postgres: remover campos do schema `escola`
        DB::connection('shared')->statement('
            ALTER TABLE escola.mensagens 
            DROP COLUMN IF EXISTS tipo,
            DROP COLUMN IF EXISTS prioridade,
            DROP COLUMN IF EXISTS anexo_url,
            DROP COLUMN IF EXISTS aluno_id,
            DROP COLUMN IF EXISTS turma_id
        ');
    }
};
