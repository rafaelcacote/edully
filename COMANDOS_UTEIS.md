# 📋 Comandos Úteis do Sistema

## 🚀 Setup Rápido (Recomendado)

Execute este comando sempre que rodar migrations e perder acesso ao sistema:

```bash
php artisan app:quick-setup
```

**O que este comando faz:**
- ✅ Executa seeders de permissões e roles
- ✅ Cria/atualiza o usuário administrador
- ✅ Configura todas as permissões
- ✅ Limpa o cache
- ✅ Garante que você tenha acesso total ao sistema

---

## 🔐 Comandos Individuais

### 1. Configurar Permissões e Roles

```bash
php artisan app:setup-permissions
```

**Opções:**
- `--fresh`: Limpa o cache de permissões antes de configurar

**O que faz:**
- Cria/atualiza todas as permissões do sistema
- Cria os 3 roles principais:
  - **Administrador Geral** (58 permissões) - Acesso total ao sistema
  - **Administrador Escola** (37 permissões) - Gestão da escola
  - **Professor** (17 permissões) - Acesso limitado para professores
- Verifica e atribui a role "Administrador Geral" ao seu usuário

### 2. Criar Usuário Administrador

```bash
php artisan app:create-admin-user
```

**O que faz:**
- Cria um usuário administrador padrão (se não existir)
- Atribui a role "Administrador Geral"
- **CPF:** 74527436287
- **Senha:** 12031986
- **Email:** admin@myschool.local

### 3. Executar Seeders

```bash
# Executar todos os seeders
php artisan db:seed

# Executar apenas o seeder de permissões
php artisan db:seed --class=PermissionsAndRolesSeeder
```

---

## 🔄 Fluxo Recomendado Após Migrations

Sempre que você executar `php artisan migrate` ou `migrate:fresh`, siga este fluxo:

```bash
# 1. Executar migrations
php artisan migrate

# 2. Restaurar permissões e acesso (RECOMENDADO)
php artisan app:quick-setup

# Ou individualmente:
# php artisan db:seed --class=PermissionsAndRolesSeeder
# php artisan app:create-admin-user
# php artisan app:setup-permissions
```

---

## 🔑 Credenciais de Acesso

### Administrador Geral
- **CPF:** 74527436287
- **Senha:** 12031986
- **Permissões:** Acesso total ao sistema

---

## 📊 Permissões por Role

### Administrador Geral (58 permissões)
Acesso total incluindo:
- Gestão de escolas/tenants
- Gestão de usuários
- Gestão de roles e permissões
- Gestão de planos e assinaturas
- Visualização de logs
- Todas as permissões de escola

### Administrador Escola (37 permissões)
Gestão completa da escola:
- Perfil da escola
- Alunos, responsáveis e professores
- Turmas e disciplinas
- Exercícios e provas
- Mensagens e avisos

### Professor (17 permissões)
Acesso limitado:
- Exercícios (CRUD)
- Provas (CRUD)
- Mensagens (CRUD)
- Avisos (CRUD)
- Disciplinas (apenas visualizar)

---

## 🧹 Comandos de Cache

```bash
# Limpar cache da aplicação
php artisan cache:clear

# Limpar cache de configuração
php artisan config:clear

# Limpar cache de permissões (Spatie)
php artisan permission:cache-reset

# Limpar todos os caches
php artisan optimize:clear
```

---

## 🐛 Solução de Problemas

### Perdi acesso ao sistema após migrations
```bash
php artisan app:quick-setup
```

### Erro "This action is unauthorized"
```bash
php artisan app:setup-permissions
php artisan permission:cache-reset
```

### Não consigo fazer login
1. Verifique se o usuário existe:
```bash
php artisan tinker
>>> User::where('cpf', '74527436287')->first();
```

2. Recriar o usuário admin:
```bash
php artisan app:create-admin-user
```

### Permissões não estão funcionando
```bash
# Limpar cache de permissões
php artisan permission:cache-reset

# Reconfigurar permissões
php artisan app:setup-permissions --fresh
```

---

## 📝 Notas Importantes

1. **Sempre execute `app:quick-setup` após migrations** para não perder acesso
2. O comando `app:quick-setup` é **idempotente** - pode ser executado múltiplas vezes sem problemas
3. Todas as permissões são criadas automaticamente pelo seeder
4. O cache de permissões é limpo automaticamente pelos comandos
5. As credenciais padrão devem ser alteradas em produção

---

## 🆘 Precisa de Ajuda?

Execute o help de qualquer comando:

```bash
php artisan app:quick-setup --help
php artisan app:setup-permissions --help
php artisan app:create-admin-user --help
```
