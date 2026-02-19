-- ============================================
-- Migration: 2026_02_13_224129_add_turma_id_to_mensagens_table
-- ============================================
-- Adiciona a coluna turma_id na tabela mensagens (PostgreSQL)

-- Verificar se o campo turma_id já existe antes de adicionar
DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 
        FROM information_schema.columns 
        WHERE table_schema = 'escola' 
        AND table_name = 'mensagens' 
        AND column_name = 'turma_id'
    ) THEN
        ALTER TABLE escola.mensagens 
        ADD COLUMN turma_id UUID;
        
        -- Criar índice para turma_id
        CREATE INDEX IF NOT EXISTS idx_mensagens_turma_id ON escola.mensagens(turma_id);
    END IF;
    
    -- Adicionar foreign key se a coluna existe e a constraint não existe
    IF EXISTS (
        SELECT 1 
        FROM information_schema.columns 
        WHERE table_schema = 'escola' 
        AND table_name = 'mensagens' 
        AND column_name = 'turma_id'
    ) AND NOT EXISTS (
        SELECT 1 
        FROM information_schema.table_constraints 
        WHERE table_schema = 'escola' 
        AND table_name = 'mensagens' 
        AND constraint_name = 'mensagens_turma_id_fkey'
    ) THEN
        ALTER TABLE escola.mensagens
        ADD CONSTRAINT mensagens_turma_id_fkey 
        FOREIGN KEY (turma_id) 
        REFERENCES escola.turmas(id) 
        ON DELETE CASCADE;
    END IF;
END
$$;

-- ============================================
-- Migration: 2026_02_18_234805_create_professor_turma_table
-- ============================================
-- Cria a tabela professor_turma no schema escola (PostgreSQL)

-- Criar schema se não existir
CREATE SCHEMA IF NOT EXISTS escola;

-- Criar tabela professor_turma
CREATE TABLE IF NOT EXISTS escola.professor_turma (
    id UUID PRIMARY KEY,
    tenant_id UUID NOT NULL,
    professor_id UUID NOT NULL,
    turma_id UUID NOT NULL,
    created_at TIMESTAMP,
    UNIQUE (tenant_id, professor_id, turma_id),
    CONSTRAINT professor_turma_tenant_id_fkey FOREIGN KEY (tenant_id) REFERENCES shared.tenants(id) ON DELETE CASCADE,
    CONSTRAINT professor_turma_professor_id_fkey FOREIGN KEY (professor_id) REFERENCES escola.professores(id) ON DELETE CASCADE,
    CONSTRAINT professor_turma_turma_id_fkey FOREIGN KEY (turma_id) REFERENCES escola.turmas(id) ON DELETE CASCADE
);

-- Criar índices
CREATE INDEX IF NOT EXISTS idx_professor_turma_tenant_id ON escola.professor_turma(tenant_id);
CREATE INDEX IF NOT EXISTS idx_professor_turma_professor_id ON escola.professor_turma(professor_id);
CREATE INDEX IF NOT EXISTS idx_professor_turma_turma_id ON escola.professor_turma(turma_id);

-- Adicionar foreign keys se a tabela já existir mas as constraints não existirem
DO $$
BEGIN
    -- Verificar e adicionar foreign key para tenant_id
    IF EXISTS (
        SELECT 1 FROM information_schema.tables 
        WHERE table_schema = 'escola' AND table_name = 'professor_turma'
    ) AND NOT EXISTS (
        SELECT 1 FROM information_schema.table_constraints 
        WHERE table_schema = 'escola' 
        AND table_name = 'professor_turma' 
        AND constraint_name = 'professor_turma_tenant_id_fkey'
    ) THEN
        ALTER TABLE escola.professor_turma
        ADD CONSTRAINT professor_turma_tenant_id_fkey 
        FOREIGN KEY (tenant_id) 
        REFERENCES shared.tenants(id) 
        ON DELETE CASCADE;
    END IF;
    
    -- Verificar e adicionar foreign key para professor_id
    IF EXISTS (
        SELECT 1 FROM information_schema.tables 
        WHERE table_schema = 'escola' AND table_name = 'professor_turma'
    ) AND NOT EXISTS (
        SELECT 1 FROM information_schema.table_constraints 
        WHERE table_schema = 'escola' 
        AND table_name = 'professor_turma' 
        AND constraint_name = 'professor_turma_professor_id_fkey'
    ) THEN
        ALTER TABLE escola.professor_turma
        ADD CONSTRAINT professor_turma_professor_id_fkey 
        FOREIGN KEY (professor_id) 
        REFERENCES escola.professores(id) 
        ON DELETE CASCADE;
    END IF;
    
    -- Verificar e adicionar foreign key para turma_id
    IF EXISTS (
        SELECT 1 FROM information_schema.tables 
        WHERE table_schema = 'escola' AND table_name = 'professor_turma'
    ) AND NOT EXISTS (
        SELECT 1 FROM information_schema.table_constraints 
        WHERE table_schema = 'escola' 
        AND table_name = 'professor_turma' 
        AND constraint_name = 'professor_turma_turma_id_fkey'
    ) THEN
        ALTER TABLE escola.professor_turma
        ADD CONSTRAINT professor_turma_turma_id_fkey 
        FOREIGN KEY (turma_id) 
        REFERENCES escola.turmas(id) 
        ON DELETE CASCADE;
    END IF;
END
$$;
