# FAFAR Contact Form 7 CRUD

## Sobre

Plugin para criação de CRUD com o Contact Form 7

### Criando

O plugin **FAFAR Contact Form 7 CRUD** salva os envios do formulário de contato 7 em seu banco de dados WordPress.

### Lendo

O plugin "FAFAR CF7CRUD" cria um shortcode simples para mostrar um determinado envio por seu 'id'.

### Editando

Este plugin lê o formulário CF7, procurando por uma entrada oculta com name='id'.
Se existir, "FAFAR CF7CRUD" sabe que é um formulário de atualização.

### Deletando

Disponibiliza um botão por meio de um shortcode para excluir um envio por 'id'.

## Banco de Dados

fafar_cf7crud_submissions:

- id VARCHAR(255) (NOT NULL | PRIMARY KEY)
- form_id INT(20) (NOT NULL)
- data JSON (NOT NULL)
- is_active INT(1) NOT NULL DEFAULT 1
- updated_at TIMESTAMP (NOT NULL | DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP)
- created_at TIMESTAMP (NOT NULL | DEFAULT CURRENT_TIMESTAMP)

## Arquivos

Cada input[type=file] se torna uma entrada personalizada para melhor controle, na criação e atualização de formulários.

### Pasta de Upload

Caminho: [...]wp-content/uploads/fafar-cf7crud-uploads/

## Não Suportado

- Drag and Drop Multiple File Upload - Contact Form 7

## Instalação

1. Baixe e extraia os arquivos do plugin para um diretório wp-content/plugin.
2. Ative o plugin através da interface de administração do WordPress.
3. Pronto!

## Changelog

### 1.0.0

### 1.0.1
