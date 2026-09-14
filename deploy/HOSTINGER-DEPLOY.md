# Deploy Edully na Hostinger (VPS) — registro do que foi feito

Documento de registro do primeiro deploy em produção/homologação no VPS Hostinger
(`srv1111626`, IP público usado: `72.61.45.28`).

Complementa o guia técnico em [`deploy/README.md`](./README.md).  
Use este arquivo depois para auditar se o setup ficou completo e conforme.

---

## 1. Decisão de infraestrutura

| Opção | Decisão |
|-------|---------|
| Hospedagem compartilhada + MySQL | **Descartada** para o Edully (Laravel 12 + Inertia + API + filas + multi-tenant) |
| VPS anual (~R$ 395) | **Escolhida** — controle de Docker, queue, cron, HTTPS, isolamento |
| Banco | **Postgres 18 em container** por ambiente (não o Postgres do host) |
| Dump do banco local na VPS | **Não recomendado** como fluxo padrão — preferir migrations versionadas |

Motivo principal: o projeto precisa de worker de fila, scheduler, build Vite e schemas Postgres (`shared`, `escola`, `laravel`, `saas`). Compartilhada limitaria isso e geraria retrabalho.

---

## 2. Arquitetura no servidor

```text
Internet
   │
   ▼
Nginx do host (CloudPanel)  :80 / :443
   │
   ├── homolog.agendaedully.com.br  → 127.0.0.1:8081  (staging / develop)
   └── app.agendaedully.com.br      → 127.0.0.1:8082  (production / master)

/opt/apps/edully/
├── staging/      # git clone -b develop
│   └── deploy/staging/   # docker compose + .env + postgres volume
└── production/   # git clone -b master
    └── deploy/production/
```

- Homolog e produção **não compartilham** volume nem banco.
- Outros sites do painel (`cerberus*`, `visaosis*`, `edully.cassote.com`, etc.) continuam como estavam.
- Repo: `https://github.com/rafaelcacote/edully.git`

| Ambiente | Domínio | Branch | Porta | Compose project |
|----------|---------|--------|-------|-----------------|
| Homologação | `https://homolog.agendaedully.com.br` | `develop` | `8081` | `edully-staging` |
| Produção | `https://app.agendaedully.com.br` | `master` | `8082` | `edully-production` |

---

## 3. Passos executados (ordem real)

### 3.1 Clone e containers

```bash
mkdir -p /opt/apps/edully

# Produção
git clone -b master https://github.com/rafaelcacote/edully.git /opt/apps/edully/production
cd /opt/apps/edully/production
cp deploy/production/.env.example deploy/production/.env
# editar .env (ver seção 4)
cd deploy/production
docker compose up -d --build
docker compose exec app php artisan migrate --force

# Homologação
git clone -b develop https://github.com/rafaelcacote/edully.git /opt/apps/edully/staging
cd /opt/apps/edully/staging
cp deploy/staging/.env.example deploy/staging/.env
# editar .env
cd deploy/staging
docker compose up -d --build
# staging: RUN_MIGRATIONS=true (migrate no boot)
```

**Atenção:** não clonar em `/root/edully`. O layout correto é `/opt/apps/edully/{staging|production}`.

### 3.2 Nginx do host (CloudPanel)

Neste servidor o Nginx só inclui arquivos `*.conf`.

```bash
# ERRADO (foi tentado e ignorado pelo Nginx):
# /etc/nginx/sites-enabled/edully

# CERTO:
sudo cp .../deploy/proxy/nginx-host.conf /etc/nginx/sites-available/edully.conf
sudo ln -sf /etc/nginx/sites-available/edully.conf /etc/nginx/sites-enabled/edully.conf
sudo nginx -t && sudo systemctl reload nginx
```

Validação crítica:

```bash
sudo nginx -T 2>/dev/null | grep -n "agendaedully\|8081\|8082"
```

Se o `grep` não mostrar os `server_name`, o vhost **não está carregado**.

Depois do certbot da produção, o homolog foi **adicionado manualmente** no final do `edully.conf` (sem sobrescrever o SSL do `app.`), apontando para `8081`.

### 3.3 SSL (Let's Encrypt)

```bash
sudo apt update
sudo apt install -y certbot python3-certbot-nginx

sudo certbot --nginx -d app.agendaedully.com.br
sudo certbot --nginx -d homolog.agendaedully.com.br
```

Após o certbot, conferir no bloco `listen 443` se permanece:

```nginx
proxy_set_header X-Forwarded-Proto $scheme;
# ou: proxy_set_header X-Forwarded-Proto https;
```

### 3.4 Usuário admin e seed

```bash
# roles/permissões
docker compose exec app php artisan db:seed --force

# usuário Administrador Geral (CPF só dígitos)
docker compose exec app php artisan tinker --execute="..."
```

Login web: CPF (`745.274.362-87` / `74527436287`) + senha definida no tinker.

---

## 4. Variáveis de ambiente obrigatórias

Arquivos:

- Homolog: `/opt/apps/edully/staging/deploy/staging/.env`
- Prod: `/opt/apps/edully/production/deploy/production/.env`

| Variável | Homolog | Produção | Notas |
|----------|---------|----------|-------|
| `APP_ENV` | `staging` | `production` | Homolog mostra faixa “somente para testes” |
| `APP_DEBUG` | `true` (compose) | `false` (compose) | Compose sobrescreve debug |
| `APP_KEY` | `base64:...` **única** | `base64:...` **única** | Não reutilizar a do PC local |
| `APP_URL` | `https://homolog.agendaedully.com.br` | `https://app.agendaedully.com.br` | Sem isso → Mixed Content |
| `DB_HOST` | `postgres` | `postgres` | Nome do serviço Docker |
| `DB_DATABASE` | `edully_staging` | `edully_production` | |
| `DB_PASSWORD` | senha forte | senha forte | |
| `DB_SCHEMA` | `escola,laravel,saas,shared` | igual | search_path da conexão default |
| `DB_SHARED_SEARCH_PATH` | `shared,escola,laravel,saas` | igual | **Obrigatório** — `shared` primeiro |
| `RUN_MIGRATIONS` | `true` | `false` | Prod: migrate manual |

Gerar key válida:

```bash
docker compose exec app php artisan key:generate --show
# cola: APP_KEY=base64:...
```

---

## 5. Problemas encontrados e correções

### 5.1 `shared.tenants` does not exist (migrate)

**Causa:** `DB_SCHEMA` começava com `escola`. A conexão `shared` reutilizava o mesmo env e criava `escola.tenants` em vez de `shared.tenants`.

**Correção no código:** `config/database.php` passou a usar `DB_SHARED_SEARCH_PATH` com `shared` primeiro.

**Correção na VPS:** adicionar a variável no `.env` e, se o banco ficou parcial:

```bash
docker compose down -v   # só em ambiente sem dados reais
docker compose up -d --build
docker compose exec app php artisan migrate --force   # produção
```

### 5.2 `APP_KEY` inválida / cipher error

**Causa:** key sem prefixo `base64:` ou comprimento errado (`openssl rand` colado cru).

**Sintoma:** HTTP 500 — `Unsupported cipher or incorrect key length`.

**Correção:** `php artisan key:generate --show` + recreate do container.

### 5.3 Nginx `ERR_EMPTY_RESPONSE` / vhost ignorado

**Causa:** arquivo sem extensão `.conf` (CloudPanel inclui `*.conf`).

**Sintoma:** `curl http://127.0.0.1:8082` ok, mas `Host: app.agendaedully...` na porta 80 → empty reply; `nginx -T` sem `agendaedully`.

### 5.4 HTTPS Mixed Content (tela branca)

**Causa:** Nginx termina SSL; o container via request HTTP e gerava assets `http://...`.

**Correções no código:**

- `bootstrap/app.php` → `$middleware->trustProxies(at: '*');`
- `AppServiceProvider` → `URL::forceScheme('https')` quando `APP_URL` é https

**Correção operacional:** `APP_URL=https://...` + rebuild/recreate + `config:clear` / `config:cache`.

### 5.5 Homolog em loop `Restarting` + 502

**Causa:** `RUN_MIGRATIONS=true` + migrate falhando (`shared.tenants`) → entrypoint sai com erro → restart loop.

**Correção:** mesmo fix do search_path + `down -v` + rebuild.

### 5.6 Dashboard admin: `saas.planos` does not exist

**Causa:** models `Plan` / `Subscription` existiam sem migration no repositório.

**Correção:** migration `2026_09_14_120000_create_saas_planos_and_assinaturas_tables.php` (+ `getTable()` sqlite-safe nos models).

**Ação na VPS após push:**

```bash
git pull
docker compose up -d --build
docker compose exec app php artisan migrate --force
```

### 5.7 Faixa de homologação

Em `resources/views/app.blade.php`, quando `APP_ENV=staging`, faixa sticky:

> Ambiente de homologação — somente para testes. Dados podem ser apagados a qualquer momento.

---

## 6. Comandos do dia a dia

```bash
# Homologação
cd /opt/apps/edully/staging
git pull origin develop
cd deploy/staging
docker compose up -d --build
docker compose exec app php artisan migrate --force   # se RUN_MIGRATIONS falhar/for false

# Produção
cd /opt/apps/edully/production
git pull origin master
cd deploy/production
docker compose up -d --build
docker compose exec app php artisan migrate --force
```

Health checks rápidos:

```bash
docker compose ps
curl -I http://127.0.0.1:8081/login   # homolog
curl -I http://127.0.0.1:8082/login   # prod
curl -I https://homolog.agendaedully.com.br/login
curl -I https://app.agendaedully.com.br/login
```

Backup Postgres (exemplo produção):

```bash
cd /opt/apps/edully/production/deploy/production
docker compose exec postgres pg_dump -U edully edully_production > backup-$(date +%F).sql
```

---

## 7. Checklist de conformidade (para auditoria posterior)

Marque o que já está ok / o que ainda falta:

### Infra e DNS

- [ ] DNS `app.agendaedully.com.br` → IP do VPS
- [ ] DNS `homolog.agendaedully.com.br` → IP do VPS
- [ ] Containers staging Up (não `Restarting`)
- [ ] Containers production Up
- [ ] Postgres healthy nos dois ambientes
- [ ] Portas só em `127.0.0.1:8081` e `127.0.0.1:8082` (não expostas publicamente)

### Nginx / SSL

- [ ] Arquivo `edully.conf` (com `.conf`) em `sites-enabled`
- [ ] `nginx -T` lista homolog + app + 8081 + 8082
- [ ] Certificados Let's Encrypt para app e homolog
- [ ] `X-Forwarded-Proto` presente nos blocos 443
- [ ] HTTP redireciona para HTTPS (comportamento do certbot)

### App / .env

- [ ] `APP_KEY` distinta por ambiente, formato `base64:...`
- [ ] `APP_URL` com `https://` em cada ambiente
- [ ] `DB_SHARED_SEARCH_PATH=shared,escola,laravel,saas`
- [ ] Senhas DB fortes e **não** reutilizadas / não commitadas
- [ ] Código com `trustProxies` + `forceScheme` deployado
- [ ] Migration `saas.planos` / `saas.assinaturas` aplicada
- [ ] `php artisan migrate` sem falhas pendentes
- [ ] Seed de roles/permissões executado
- [ ] Usuário admin geral criado e login ok
- [ ] Homolog mostra faixa de ambiente de testes
- [ ] Produção **não** mostra faixa de homolog
- [ ] Assets `/build/...` carregam em HTTPS (sem Mixed Content)

### Operação / segurança (recomendado revisar)

- [ ] Backup automático do Postgres (cron)
- [ ] Backup de volumes `storage` (uploads)
- [ ] Rotação da senha DB se vazou em chat/logs
- [ ] `APP_DEBUG=false` em produção confirmado
- [ ] Firewall (só 22/80/443 públicos, se aplicável)
- [ ] Renovação certbot (`certbot.timer` ativo)
- [ ] Processo de deploy documentado para o time
- [ ] Staging e production em branches corretas (`develop` / `master`)

### Ainda não feito / opcional neste MVP

- [ ] Monitoramento (uptime / logs centralizados)
- [ ] Staging e prod com secrets fora do `.env` plain (ex.: Docker secrets)
- [ ] CDN / object storage para anexos
- [ ] Gateway de pagamento (módulo financeiro ainda sem gateway no MVP)
- [ ] Push FCM completo em produção (há Expo push no código; validar tokens)

---

## 8. Arquivos de código relacionados a este deploy

| Arquivo | Papel |
|---------|--------|
| `deploy/README.md` | Layout e comandos base |
| `deploy/HOSTINGER-DEPLOY.md` | Este registro + checklist |
| `deploy/docker/*` | Imagem app (nginx+php+queue+scheduler) |
| `deploy/staging/*` | Compose + env homolog |
| `deploy/production/*` | Compose + env produção |
| `deploy/proxy/nginx-host.conf` | Template dos vhosts |
| `config/database.php` | `DB_SHARED_SEARCH_PATH` |
| `bootstrap/app.php` | `trustProxies` |
| `app/Providers/AppServiceProvider.php` | `URL::forceScheme('https')` |
| `resources/views/app.blade.php` | Faixa de homologação |
| `database/migrations/2026_09_14_120000_create_saas_planos_and_assinaturas_tables.php` | Tabelas SaaS faltantes |

---

## 9. Histórico resumido desta subida

1. Escolha VPS em vez de compartilhada.
2. Setup Docker staging/production em `/opt/apps/edully`.
3. Correção do clone errado em `/root`.
4. Migrate produção falhou em `shared.tenants` → fix search_path.
5. Nginx sem `.conf` → empty response → renomeado para `edully.conf`.
6. Certbot app + homolog.
7. Mixed Content HTTPS → `APP_URL` + trustProxies + forceScheme.
8. Homolog restart loop pela migrate → reset volume + fix.
9. Seed + usuário admin por CPF.
10. Erro `saas.planos` → migration criada (pendente aplicar na VPS após push).
11. Faixa visual de homologação.

Quando pedir a análise “se fizemos tudo nos conformes”, use a **seção 7** como base da auditoria.
