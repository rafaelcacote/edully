# 🎓 Edully - Sistema de Gestão Escolar

<div align="center">

![Laravel](https://img.shields.io/badge/Laravel-12.x-FF2D20?style=for-the-badge&logo=laravel&logoColor=white)
![Vue.js](https://img.shields.io/badge/Vue.js-3.5-4FC08D?style=for-the-badge&logo=vue.js&logoColor=white)
![Inertia.js](https://img.shields.io/badge/Inertia.js-2.0-9553E9?style=for-the-badge)
![Tailwind CSS](https://img.shields.io/badge/Tailwind_CSS-4.0-38B2AC?style=for-the-badge&logo=tailwind-css&logoColor=white)
![PHP](https://img.shields.io/badge/PHP-8.3-777BB4?style=for-the-badge&logo=php&logoColor=white)
![TypeScript](https://img.shields.io/badge/TypeScript-5.2-3178C6?style=for-the-badge&logo=typescript&logoColor=white)

**Uma plataforma completa e moderna para gestão escolar multi-tenant**

[Características](#-características) • [Tecnologias](#-tecnologias) • [Instalação](#-instalação) • [Uso](#-uso) • [API](#-api)

</div>

---

## 📋 Sobre o Projeto

O **Edully** é um sistema de gestão escolar completo desenvolvido com as mais modernas tecnologias web. A plataforma oferece uma solução robusta para administração de escolas, permitindo gerenciar alunos, professores, turmas, disciplinas, provas, exercícios, mensagens e muito mais.

### ✨ Principais Funcionalidades

- 🏫 **Multi-tenancy**: Suporte para múltiplas escolas em uma única instalação
- 👥 **Gestão de Usuários**: Administradores, professores, alunos e responsáveis
- 📚 **Gestão Acadêmica**: Turmas, disciplinas, provas e exercícios
- 📊 **Sistema de Notas**: Controle completo de avaliações e notas
- 💬 **Comunicação**: Mensagens e avisos entre professores, alunos e responsáveis
- 🔐 **Controle de Acesso**: Sistema robusto de permissões e roles
- 📱 **API Mobile**: API REST completa para integração com aplicativos móveis
- 💳 **Planos e Assinaturas**: Sistema de planos para diferentes tipos de escolas
- 📝 **Auditoria**: Logs completos de todas as ações do sistema

---

## 🚀 Características

### Para Administradores
- Gestão completa de escolas (tenants)
- Controle de usuários, roles e permissões
- Gerenciamento de planos e assinaturas
- Visualização de logs de auditoria
- Dashboard com métricas do sistema

### Para Administradores de Escola
- Perfil e configurações da escola
- Gestão de alunos, professores e responsáveis
- Criação e gerenciamento de turmas e disciplinas
- Controle de provas e exercícios
- Sistema de mensagens e avisos
- Gestão de notas e avaliações

### Para Professores
- Criação e gerenciamento de provas
- Criação e gerenciamento de exercícios
- Envio de mensagens e avisos
- Visualização de turmas e disciplinas
- Acesso às informações dos alunos

### Para Responsáveis
- Visualização de provas dos filhos
- Acompanhamento acadêmico
- Comunicação com professores
- Recebimento de avisos e mensagens

---

## 🛠 Tecnologias

### Backend
- **Laravel 12** - Framework PHP moderno e robusto
- **PHP 8.3** - Linguagem de programação
- **Laravel Sanctum** - Autenticação API
- **Laravel Fortify** - Autenticação completa
- **Spatie Permission** - Sistema de permissões e roles
- **Laravel Wayfinder** - Geração de rotas type-safe para frontend

### Frontend
- **Vue.js 3** - Framework JavaScript reativo
- **Inertia.js 2** - SPA sem API
- **TypeScript** - Tipagem estática
- **Tailwind CSS 4** - Framework CSS utility-first
- **Reka UI** - Componentes UI modernos
- **Lucide Icons** - Ícones vetoriais

### Ferramentas de Desenvolvimento
- **Pest 4** - Framework de testes PHP
- **Laravel Pint** - Code formatter
- **ESLint** - Linter JavaScript/TypeScript
- **Prettier** - Formatador de código
- **Vite** - Build tool moderna

---

## 📦 Instalação

### Pré-requisitos

- PHP >= 8.3
- Composer
- Node.js >= 18.x e npm
- Banco de dados (MySQL, PostgreSQL ou SQLite)

### Passo a Passo

1. **Clone o repositório**
```bash
git clone https://github.com/rafaelcacote/eduly.git
cd eduly
```

2. **Instale as dependências PHP**
```bash
composer install
```

3. **Instale as dependências Node.js**
```bash
npm install
```

4. **Configure o ambiente**
```bash
cp .env.example .env
php artisan key:generate
```

5. **Configure o banco de dados no arquivo `.env`**
```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=eduly
DB_USERNAME=seu_usuario
DB_PASSWORD=sua_senha
```

6. **Execute as migrations**
```bash
php artisan migrate
```

7. **Configure o sistema (permissões, roles e usuário admin)**
```bash
php artisan app:quick-setup
```

8. **Compile os assets**
```bash
npm run build
```

9. **Inicie o servidor de desenvolvimento**
```bash
composer run dev
```

Ou use os comandos separados:
```bash
# Terminal 1: Servidor PHP
php artisan serve

# Terminal 2: Vite (desenvolvimento frontend)
npm run dev

# Terminal 3: Queue Worker (se necessário)
php artisan queue:work
```

---

## 🎯 Uso

### Credenciais Padrão

Após executar `php artisan app:quick-setup`, você pode acessar o sistema com:

- **CPF:** `74527436287`
- **Senha:** `12031986`
- **Email:** `admin@myschool.local`

> ⚠️ **Importante:** Altere essas credenciais em produção!

### Comandos Úteis

#### Setup Rápido
```bash
# Configura tudo: seeders + usuário admin + permissões
php artisan app:quick-setup
```

#### Comandos Individuais
```bash
# Configurar permissões e roles
php artisan app:setup-permissions

# Criar usuário administrador
php artisan app:create-admin-user

# Executar seeders
php artisan db:seed --class=PermissionsAndRolesSeeder
```

#### Limpar Cache
```bash
# Limpar todos os caches
php artisan optimize:clear

# Limpar cache de permissões
php artisan permission:cache-reset
```

Para mais informações, consulte o arquivo [COMANDOS_UTEIS.md](./COMANDOS_UTEIS.md).

---

## 📱 API

O Edully possui uma API REST completa para integração com aplicativos móveis. A API utiliza autenticação via **Bearer Token** (Laravel Sanctum).

### Documentação da API

A documentação completa da API de Provas está disponível em [API_PROVAS_RESUMO.md](./API_PROVAS_RESUMO.md).

### Exemplo de Uso

```bash
# Autenticar e obter token
POST /api/login
{
  "cpf": "12345678900",
  "password": "senha123"
}

# Listar provas
GET /api/mobile/tests
Authorization: Bearer {token}

# Criar prova (apenas professores)
POST /api/mobile/tests
Authorization: Bearer {token}
Content-Type: application/json
{
  "disciplina_id": "uuid",
  "titulo": "Prova de Matemática",
  "data_prova": "2024-03-15",
  "turma_id": "uuid"
}
```

---

## 🧪 Testes

O projeto utiliza **Pest 4** para testes. Execute os testes com:

```bash
# Executar todos os testes
php artisan test

# Executar testes de um arquivo específico
php artisan test tests/Feature/ExampleTest.php

# Executar testes com filtro
php artisan test --filter=testName
```

---

## 📁 Estrutura do Projeto

```
eduly/
├── app/
│   ├── Actions/          # Actions do Fortify e outras
│   ├── Console/          # Comandos Artisan
│   ├── Http/
│   │   ├── Controllers/  # Controladores
│   │   ├── Middleware/   # Middlewares
│   │   ├── Requests/     # Form Requests (validação)
│   │   └── Resources/    # API Resources
│   ├── Models/           # Modelos Eloquent
│   └── Providers/        # Service Providers
├── bootstrap/            # Arquivos de inicialização
├── config/               # Arquivos de configuração
├── database/
│   ├── factories/        # Model Factories
│   ├── migrations/      # Migrations
│   └── seeders/         # Seeders
├── public/               # Arquivos públicos
├── resources/
│   ├── css/              # Estilos CSS
│   ├── js/
│   │   ├── components/   # Componentes Vue
│   │   └── pages/        # Páginas Inertia
│   └── views/            # Views Blade
├── routes/               # Rotas da aplicação
└── tests/                # Testes
```

---

## 🔐 Sistema de Permissões

O sistema utiliza **Spatie Laravel Permission** para controle de acesso granular. Existem três roles principais:

### Administrador Geral
- 58 permissões
- Acesso total ao sistema
- Gestão de escolas, usuários, roles e permissões

### Administrador Escola
- 37 permissões
- Gestão completa da escola
- Alunos, professores, turmas, disciplinas, provas, etc.

### Professor
- 17 permissões
- Acesso limitado para atividades docentes
- Provas, exercícios, mensagens e avisos

---

## 🎨 Interface

A interface foi desenvolvida com:
- **Tailwind CSS 4** para estilização
- **Reka UI** para componentes modernos
- **Lucide Icons** para ícones
- Design responsivo e suporte a dark mode
- Experiência de usuário otimizada

---

## 🤝 Contribuindo

Contribuições são bem-vindas! Sinta-se à vontade para:

1. Fazer um Fork do projeto
2. Criar uma branch para sua feature (`git checkout -b feature/AmazingFeature`)
3. Commit suas mudanças (`git commit -m 'Add some AmazingFeature'`)
4. Push para a branch (`git push origin feature/AmazingFeature`)
5. Abrir um Pull Request

---

## 📄 Licença

Este projeto está sob a licença MIT. Veja o arquivo [LICENSE](LICENSE) para mais detalhes.

---

## 👨‍💻 Autor

**Rafael Cacote**

- GitHub: [@rafaelcacote](https://github.com/rafaelcacote)
- Repositório: [https://github.com/rafaelcacote/eduly](https://github.com/rafaelcacote/eduly)

---

## 🙏 Agradecimentos

- [Laravel](https://laravel.com) - Framework PHP incrível
- [Vue.js](https://vuejs.org) - Framework JavaScript progressivo
- [Inertia.js](https://inertiajs.com) - SPA sem API
- [Tailwind CSS](https://tailwindcss.com) - Framework CSS utility-first
- [Spatie](https://spatie.be) - Pacotes Laravel de qualidade

---

<div align="center">

**Feito com ❤️ usando Laravel e Vue.js**

⭐ Se este projeto foi útil para você, considere dar uma estrela!

</div>
