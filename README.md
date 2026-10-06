# Gestor de Empresas, Produtos e Estoque

Aplicação web completa para cadastro de empresas e gestão de produtos e estoque, com dashboard consolidado.
Os estados e municípios são obtidos em tempo real da [API de Localidades do IBGE](https://servicodados.ibge.gov.br/api/docs/localidades).

## Tecnologias
- **Backend:** PHP 8.1+ (API REST com PDO)
- **Banco de dados:** MySQL
- **Frontend:** HTML, CSS e JavaScript puro
- **API externa:** IBGE Localidades

## Arquitetura
```
Frontend (HTML/JS) → Backend (API REST em PHP) → MySQL + API do IBGE
```
O frontend se comunica com o backend exclusivamente via API REST.

## Dependências
- PHP 8.1 ou superior, com a extensão `pdo_mysql` ativa
- MySQL 5.7+ ou 8
- Conexão com a internet (para carregar estados e municípios do IBGE)

O [XAMPP](https://www.apachefriends.org) traz PHP e MySQL juntos.

## Como rodar localmente

### 1. Clonar o projeto
```
git clone https://github.com/Lcxm2002/gestor-estoque.git
cd gestor-estoque
```

### 2. Iniciar o MySQL
Ligue o MySQL (no XAMPP, clique em **Start** ao lado de MySQL).

### 3. Criar o banco de dados
```
mysql -u root -p < schema.sql
```
No XAMPP, sem senha:
```
C:\xampp\mysql\bin\mysql -u root < schema.sql
```
O script cria o banco `gestor_estoque` com as tabelas `empresas` e `produtos`.

### 4. Configurar as variáveis de ambiente
Copie o exemplo e ajuste usuário e senha, se necessário:
```
copy .env.example .env      # Windows
cp .env.example .env        # Linux/Mac
```

### 5. Iniciar o servidor
```
php -S localhost:8000 -t public public/router.php
```

### 6. Acessar
Abra **http://localhost:8000** no navegador.

## Variáveis de ambiente (`.env`)
| Variável | Descrição | Padrão |
|---|---|---|
| DB_HOST | Servidor do MySQL | 127.0.0.1 |
| DB_PORT | Porta do MySQL | 3306 |
| DB_NAME | Nome do banco | gestor_estoque |
| DB_USER | Usuário | root |
| DB_PASS | Senha | (vazio) |

## Modelagem do banco
```
empresas (1) ──< (N) produtos
```
- `empresas`: id, razao_social, cnpj (único), nome_fantasia, endereco, uf, municipio
- `produtos`: id, empresa_id (FK), nome, sku, preco, quantidade
- SKU é único por empresa; ao excluir uma empresa, seus produtos são removidos (`ON DELETE CASCADE`).

## Endpoints da API
| Método | Rota | Descrição |
|---|---|---|
| GET | `/api/empresas` | Lista as empresas |
| POST | `/api/empresas` | Cadastra uma empresa |
| GET | `/api/produtos?empresa_id=1` | Lista produtos (filtro opcional por empresa) |
| GET | `/api/produtos/{id}` | Detalha um produto |
| POST | `/api/produtos` | Cria um produto |
| PUT | `/api/produtos/{id}` | Atualiza um produto |
| DELETE | `/api/produtos/{id}` | Exclui um produto |
| GET | `/api/dashboard/stats` | Totais e resumo por empresa |

## Funcionalidades
- Cadastro de empresas com Estado e Município vindos do IBGE
- Validação de CNPJ (dígitos verificadores) e de duplicidade
- CRUD de produtos vinculados a uma empresa, com filtro
- Dashboard com total de empresas, total de produtos, valor em estoque e lista de itens por empresa

## Decisões técnicas
- Consultas com *prepared statements* (proteção contra SQL injection)
- Escape de HTML no frontend (proteção contra XSS)
- Sem framework, para manter o código simples e fácil de ler