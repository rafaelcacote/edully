# API de Provas - Documentação para Integração Mobile

## Base URL
```
/api/mobile/tests
```

## Autenticação
Todas as rotas requerem autenticação via **Bearer Token** (Sanctum).
```
Authorization: Bearer {token}
```

## Endpoints Disponíveis

### 1. Listar Provas
**GET** `/api/mobile/tests`

Lista provas conforme o tipo de usuário:
- **Professores**: Ver apenas provas que criaram
- **Responsáveis**: Ver provas das turmas dos seus alunos

#### Query Parameters (opcionais)
- `turma_id` (UUID) - Filtrar por turma
- `disciplina_id` (UUID) - Filtrar por disciplina
- `aluno_id` (UUID) - Filtrar por aluno (apenas para responsáveis)

#### Resposta de Sucesso (200)
```json
{
  "tests": [
    {
      "id": "uuid",
      "titulo": "Prova de Matemática",
      "descricao": "Prova sobre equações",
      "data_prova": "2024-03-15",
      "data_prova_formatted": "15/03/2024",
      "horario": "08:00",
      "sala": "101",
      "duracao_minutos": 90,
      "disciplina": {
        "id": "uuid",
        "nome": "Matemática",
        "sigla": "MAT"
      },
      "turma": {
        "id": "uuid",
        "nome": "1º Ano A",
        "serie": "1º Ano",
        "turma_letra": "A",
        "ano_letivo": 2024
      },
      "professor": {
        "id": "uuid",
        "usuario": {
          "id": "uuid",
          "nome_completo": "João Silva"
        }
      },
      "created_at": "2024-02-16T01:00:00.000000Z",
      "updated_at": "2024-02-16T01:00:00.000000Z"
    }
  ],
  "meta": {
    "current_page": 1,
    "last_page": 1,
    "per_page": 15,
    "total": 1
  }
}
```

---

### 2. Ver Detalhes de uma Prova
**GET** `/api/mobile/tests/{id}`

#### Resposta de Sucesso (200)
```json
{
  "test": {
    "id": "uuid",
    "titulo": "Prova de Matemática",
    "descricao": "Prova sobre equações",
    "data_prova": "2024-03-15",
    "data_prova_formatted": "15/03/2024",
    "horario": "08:00",
    "sala": "101",
    "duracao_minutos": 90,
    "disciplina": {
      "id": "uuid",
      "nome": "Matemática",
      "sigla": "MAT"
    },
    "turma": {
      "id": "uuid",
      "nome": "1º Ano A",
      "serie": "1º Ano",
      "turma_letra": "A",
      "ano_letivo": 2024
    },
    "professor": {
      "id": "uuid",
      "usuario": {
        "id": "uuid",
        "nome_completo": "João Silva"
      }
    },
    "created_at": "2024-02-16T01:00:00.000000Z",
    "updated_at": "2024-02-16T01:00:00.000000Z"
  }
}
```

#### Resposta de Erro (403)
```json
{
  "message": "Prova não encontrada ou você não tem permissão para acessá-la."
}
```

#### Resposta de Erro (404)
```json
{
  "message": "No query results for model [App\\Models\\Test] {id}"
}
```

---

### 3. Criar Prova
**POST** `/api/mobile/tests`

**Apenas para Professores**

#### Body (JSON)
```json
{
  "disciplina_id": "uuid",
  "titulo": "Prova de Matemática",
  "descricao": "Prova sobre equações de segundo grau",
  "data_prova": "2024-03-15",
  "horario": "08:00",
  "sala": "101",
  "duracao_minutos": 90,
  "turma_id": "uuid"
}
```

#### Campos Obrigatórios
- `disciplina_id` (UUID) - ID da disciplina (deve ser uma disciplina vinculada ao professor)
- `titulo` (string, max 255) - Título da prova
- `data_prova` (date, formato YYYY-MM-DD) - Data da prova (deve ser hoje ou futura)
- `turma_id` (UUID) - ID da turma (deve ser uma turma do professor)

#### Campos Opcionais
- `descricao` (string) - Descrição da prova
- `horario` (string, max 10) - Horário da prova (ex: "08:00")
- `sala` (string, max 50) - Sala onde será realizada
- `duracao_minutos` (integer, 1-600) - Duração em minutos

#### Resposta de Sucesso (201)
```json
{
  "message": "Prova criada com sucesso.",
  "test": {
    "id": "uuid",
    "titulo": "Prova de Matemática",
    "descricao": "Prova sobre equações de segundo grau",
    "data_prova": "2024-03-15",
    "data_prova_formatted": "15/03/2024",
    "horario": "08:00",
    "sala": "101",
    "duracao_minutos": 90,
    "disciplina": {
      "id": "uuid",
      "nome": "Matemática",
      "sigla": "MAT"
    },
    "turma": {
      "id": "uuid",
      "nome": "1º Ano A",
      "serie": "1º Ano",
      "turma_letra": "A",
      "ano_letivo": 2024
    },
    "professor": {
      "id": "uuid",
      "usuario": {
        "id": "uuid",
        "nome_completo": "João Silva"
      }
    },
    "created_at": "2024-02-16T01:00:00.000000Z",
    "updated_at": "2024-02-16T01:00:00.000000Z"
  }
}
```

#### Resposta de Erro (422) - Validação
```json
{
  "message": "The given data was invalid.",
  "errors": {
    "disciplina_id": ["Selecione uma disciplina."],
    "titulo": ["Informe o título da prova."],
    "data_prova": ["Informe a data da prova."],
    "turma_id": ["Selecione uma turma."]
  }
}
```

#### Resposta de Erro (403)
```json
{
  "message": "Acesso negado. Apenas professores podem criar provas."
}
```

---

### 4. Atualizar Prova
**PUT** `/api/mobile/tests/{id}`

**Apenas para Professores** (apenas provas que o professor criou)

#### Body (JSON)
Todos os campos são opcionais (usar `sometimes`):
```json
{
  "titulo": "Prova de Matemática - Atualizada",
  "descricao": "Nova descrição",
  "data_prova": "2024-03-20",
  "horario": "10:00",
  "sala": "202",
  "duracao_minutos": 120,
  "disciplina_id": "uuid",
  "turma_id": "uuid"
}
```

#### Resposta de Sucesso (200)
```json
{
  "message": "Prova atualizada com sucesso.",
  "test": {
    "id": "uuid",
    "titulo": "Prova de Matemática - Atualizada",
    ...
  }
}
```

#### Resposta de Erro (403)
```json
{
  "message": "Prova não encontrada ou você não tem permissão para atualizá-la."
}
```

---

### 5. Deletar Prova
**DELETE** `/api/mobile/tests/{id}`

**Apenas para Professores** (apenas provas que o professor criou)

#### Resposta de Sucesso (200)
```json
{
  "message": "Prova removida com sucesso."
}
```

#### Resposta de Erro (403)
```json
{
  "message": "Prova não encontrada ou você não tem permissão para removê-la."
}
```

---

## Códigos de Status HTTP

- `200` - Sucesso (GET, PUT, DELETE)
- `201` - Criado com sucesso (POST)
- `401` - Não autenticado (token inválido ou ausente)
- `403` - Acesso negado (sem permissão)
- `404` - Recurso não encontrado
- `422` - Erro de validação
- `500` - Erro interno do servidor

---

## Regras de Negócio

### Para Professores:
- Podem criar, editar e deletar apenas provas que criaram
- Só podem criar provas para turmas que lecionam
- Só podem criar provas para disciplinas que lecionam
- Podem filtrar por turma e disciplina

### Para Responsáveis:
- Podem apenas visualizar provas
- Veem apenas provas das turmas dos seus alunos
- Podem filtrar por aluno, turma
- Não podem criar, editar ou deletar provas

### Validações:
- `data_prova` deve ser hoje ou uma data futura
- `duracao_minutos` deve estar entre 1 e 600 minutos (10 horas)
- `disciplina_id` deve ser uma disciplina válida e vinculada ao professor
- `turma_id` deve ser uma turma válida e do professor
- `titulo` máximo de 255 caracteres
- `horario` máximo de 10 caracteres
- `sala` máximo de 50 caracteres

---

## Exemplos de Uso

### Exemplo 1: Listar todas as provas (Professor)
```http
GET /api/mobile/tests
Authorization: Bearer {token}
```

### Exemplo 2: Listar provas de uma turma específica
```http
GET /api/mobile/tests?turma_id=uuid-da-turma
Authorization: Bearer {token}
```

### Exemplo 3: Listar provas de um aluno (Responsável)
```http
GET /api/mobile/tests?aluno_id=uuid-do-aluno
Authorization: Bearer {token}
```

### Exemplo 4: Criar uma nova prova
```http
POST /api/mobile/tests
Authorization: Bearer {token}
Content-Type: application/json

{
  "disciplina_id": "uuid",
  "titulo": "Prova Bimestral de Matemática",
  "descricao": "Prova sobre álgebra e geometria",
  "data_prova": "2024-03-20",
  "horario": "14:00",
  "sala": "301",
  "duracao_minutos": 120,
  "turma_id": "uuid"
}
```

### Exemplo 5: Atualizar uma prova
```http
PUT /api/mobile/tests/uuid-da-prova
Authorization: Bearer {token}
Content-Type: application/json

{
  "titulo": "Prova Bimestral de Matemática - Atualizada",
  "horario": "15:00"
}
```

### Exemplo 6: Deletar uma prova
```http
DELETE /api/mobile/tests/uuid-da-prova
Authorization: Bearer {token}
```

---

## Notas Importantes

1. **Paginação**: A listagem retorna 15 itens por página por padrão
2. **Ordenação**: Provas são ordenadas por data (mais próximas primeiro) e depois por data de criação
3. **Soft Delete**: Provas deletadas são soft deleted (não aparecem nas listagens)
4. **Filtros**: Todos os filtros são opcionais e podem ser combinados
5. **Timezone**: Todas as datas são retornadas em formato ISO 8601 (UTC)
6. **Formato de Data**: Use `YYYY-MM-DD` para envio e receberá também `data_prova_formatted` em formato brasileiro `DD/MM/YYYY`

---

## Estrutura de Erros

### Erro de Validação (422)
```json
{
  "message": "The given data was invalid.",
  "errors": {
    "campo": ["mensagem de erro"]
  }
}
```

### Erro de Acesso (403)
```json
{
  "message": "Mensagem descritiva do erro"
}
```

### Erro de Autenticação (401)
```json
{
  "message": "Unauthenticated."
}
```
