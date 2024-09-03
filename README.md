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
- object_name VARCHAR(50)
- data JSON (NOT NULL)
- is_active INT(1) NOT NULL DEFAULT 1
- updated_at TIMESTAMP (NOT NULL | DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP)
- created_at TIMESTAMP (NOT NULL | DEFAULT CURRENT_TIMESTAMP)

### Nome do Objeto

O parâmetro 'object_name' é opcional e pode ser usado para atrelar um nome de objeto a submissão.  
Adicione um input ao formulário com o nome 'fafar-cf7crud-object-name'. Exemplo:

```
[hidden fafar-cf7crud-object-name "carro"]
```

### Banco de Dados Customizado

Use o filter hook 'fafar_cf7crud_set_database' para utilizar outro banco de dados.  
Parâmetros:  
$wpdb. WPDB Object. Instância global.

Observação: Se espera uma tabela 'fafar_cf7crud_submissions' com as respectivas colunas no banco de dados no retorno do hook.

## Segurança

### Verificação/Uso de Nonce

Para formulários privados, recomenda-se o uso da solução do Contact Form 7 via [Configurações Adicionais](https://contactform7.com/additional-settings/#subscribers-only-mode):

```
subscribers_only: on
```

### Tag's Permitidas

Para configurar tag's permitidas, use o filter hook 'fafar_cf7crud_not_allowed_tags'.  
Parâmetros:  
$allowed_tags. Array. Todas as tag's presentes no formulário.

### Campos Não Permitidos

Para configurar campos não permitidos, use o filter hook 'fafar_cf7crud_not_allowed_fields'.  
Parâmetros:  
$allowed_fields. Array. Nome dos inputs. Um item: 'g-recaptcha-response'.

## Inputs Customizados

### fafar-cf7crud-san-lb-

Adicione o prefixo 'fafar-cf7crud-san-lb-' para que quebras de linha sejam mantidas.
Utiliza-se a função [sanitize_textarea_field](https://developer.wordpress.org/reference/functions/sanitize_textarea_field/).
Exemplo:

```
[textarea fafar-cf7crud-san-lb-sobre]
```

### fafar-cf7crud-san-em-

Adicione o prefixo 'fafar-cf7crud-san-em-' para remover todos os caracteres não permitidos em um e-mail.
Utiliza-se a função [sanitize_email](https://developer.wordpress.org/reference/functions/sanitize_email/).
Exemplo:

```
[email fafar-cf7crud-san-em-email]
```

### fafar-cf7crud-san-fi-

Adicione o prefixo 'fafar-cf7crud-san-fi-' para substituir todos os espaços em branco por traços.
Utiliza-se a função [sanitize_file_name](https://developer.wordpress.org/reference/functions/sanitize_file_name/).
Exemplo:

```
[text fafar-cf7crud-san-fi-nome-arquivo]
```

### fafar-cf7crud-san-key-

Adicione o prefixo 'fafar-cf7crud-san-key-' para transformar todas as letras em minúsculos. Além disso, permite apenas sublinhados e traços.
Utiliza-se a função [sanitize_key](https://developer.wordpress.org/reference/functions/sanitize_key/).
Exemplo:

```
[text fafar-cf7crud-san-key-chave]
```

### Outros

Se nenhum prefixo conhecido for informado, utiliza-se o [sanitize_text_field](https://developer.wordpress.org/reference/functions/sanitize_text_field/).

## Arquivos

Cada input[type=file] se torna uma entrada personalizada para melhor controle, na criação e atualização de formulários.

### Prefixo

No banco de dados, o nome do arquivo é guardado dessa forma:  
'fafar-cf7crud-file-' + NOME DA PROPRIEDADE = NOME DO ARQUIVO.EXTENSÃO  
Exemplo:  
'fafar-cf7crud-file-foto':'foto.jpg'

### Pasta de Upload

Caminho: [...]wp-content/uploads/fafar-cf7crud-uploads/

## Validação Final

É possível usar o filter hook 'fafar_cf7crud_before_create' para uma última validação, antes da criação.
Parâmetro esperado: Array PHP, com todos os campos e seus respectivos valores.
Retorno esperado: Objeto PHP | null(para cancelar a criação).

## Plugins Não Suportados

- Drag and Drop Multiple File Upload - Contact Form 7

## Instalação

1. Baixe e extraia os arquivos do plugin para um diretório wp-content/plugin.
2. Ative o plugin através da interface de administração do WordPress.
3. Pronto!

## Changelog

### 1.0.0

### 1.0.1
