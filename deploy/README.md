# Layout de deploy do Edully (homologação + produção)
# Modo: Nginx do host + containers Docker + Postgres 18

> Registro detalhado do primeiro deploy na Hostinger (problemas, fixes e checklist):
> [`HOSTINGER-DEPLOY.md`](./HOSTINGER-DEPLOY.md)

## Repositório e branches

| Ambiente | Domínio | Branch | Porta local |
|----------|---------|--------|-------------|
| Homologação | http://homolog.agendaedully.com.br/ | `develop` | `127.0.0.1:8081` |
| Produção | http://app.agendaedully.com.br/ | `master` | `127.0.0.1:8082` |

Repo: https://github.com/rafaelcacote/edully.git

## Visão no VPS

```text
Internet
   │
   ▼
Nginx do host (80/443)          ← já existe com cerberus/visaosis
   │
   ├── homolog.agendaedully.com.br  → 127.0.0.1:8081  (branch develop)
   └── app.agendaedully.com.br      → 127.0.0.1:8082  (branch master)

/opt/apps/edully/
├── staging/     # clone -b develop  + postgres:18
└── production/  # clone -b master   + postgres:18
```

Homolog e prod **não compartilham** volume nem banco.
O Postgres **18 do host** continua para os outros projetos; o Edully usa
**Postgres 18 em container** por ambiente (sem mexer no `pg_hba` do host).

## O que veio no repositório

```text
deploy/
├── docker/                         # imagem: nginx+php+queue+scheduler
├── staging/
│   ├── docker-compose.yml          # porta 127.0.0.1:8081 + postgres:18
│   ├── .env.example
│   └── init-db.sql
├── production/
│   ├── docker-compose.yml          # porta 127.0.0.1:8082 + postgres:18
│   ├── .env.example
│   └── init-db.sql
└── proxy/
    └── nginx-host.conf             # vhosts para o Nginx do host
```

## Setup no VPS

### 1) Homologação (`develop`)

```bash
mkdir -p /opt/apps/edully
git clone -b develop https://github.com/rafaelcacote/edully.git /opt/apps/edully/staging
cd /opt/apps/edully/staging

cp deploy/staging/.env.example deploy/staging/.env
# preencha APP_KEY, DB_PASSWORD

cd deploy/staging
docker compose up -d --build
```

### 2) Produção (`master`)

```bash
git clone -b master https://github.com/rafaelcacote/edully.git /opt/apps/edully/production
cd /opt/apps/edully/production

cp deploy/production/.env.example deploy/production/.env
# preencha APP_KEY e senha forte do Postgres

cd deploy/production
docker compose up -d --build
```

Em produção, `RUN_MIGRATIONS=false`. Migre sob controle:

```bash
docker compose exec app php artisan migrate --force
```

### 3) Nginx do host

```bash
# Neste servidor (CloudPanel) o include é *.conf — use essa extensão.
sudo cp /opt/apps/edully/production/deploy/proxy/nginx-host.conf \
  /etc/nginx/sites-available/edully.conf
sudo ln -sf /etc/nginx/sites-available/edully.conf /etc/nginx/sites-enabled/edully.conf
sudo nginx -t && sudo systemctl reload nginx

# SSL (quando HTTP já responder)
# sudo apt install -y certbot python3-certbot-nginx
# sudo certbot --nginx -d app.agendaedully.com.br
# Depois: APP_URL=https://app.agendaedully.com.br no .env e recreate do container
```

## Deploy do dia a dia

```bash
# Homologação
cd /opt/apps/edully/staging
git pull origin develop
cd deploy/staging
docker compose up -d --build

# Produção
cd /opt/apps/edully/production
git pull origin master
cd deploy/production
docker compose up -d --build
```

## Postgres 18

- Imagem: `postgres:18-alpine`
- Volume montado em `/var/lib/postgresql` (layout novo do Postgres 18)
- Staging: `edully_staging`
- Prod: `edully_production`
- Schemas no first boot: `shared`, `escola`, `laravel`, `saas`

Backup:

```bash
docker compose exec postgres pg_dump -U edully edully_production > backup.sql
```

### Quer usar o Postgres 18 do host em vez do container?

Dá, mas o Postgres do Ubuntu escuta só em `127.0.0.1` por padrão —
o container não alcança. Aí precisa liberar o Docker bridge no
`postgresql.conf` / `pg_hba.conf`. Por isso o padrão aqui é **Postgres 18
no compose**, isolado e sem risco para os outros projetos.

## Convivendo com `/home`

`cerberus*`, `visaosis*`, etc. continuam como estão.
Só o Edully entra via Docker + estes dois `server` blocks no Nginx.
