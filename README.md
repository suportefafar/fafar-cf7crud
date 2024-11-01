# FAFAR Contact Form 7 CRUD

## Sobre

Plugin para criação de CRUD com o Contact Form 7

### Criando

O plugin **FAFAR Contact Form 7 CRUD** salva os envios do formulário de contato 7 em seu banco de dados WordPress.

### Lendo

_Não implementada até o presente momento._

### Editando

Este plugin lê o formulário CF7, procurando por uma entrada oculta com name='id'.
Se existir, "FAFAR CF7CRUD" sabe que é um formulário de atualização.

### Deletando

_Não implementada até o presente momento._

Disponibiliza um botão por meio de um shortcode para _excluir_ a _submissão_.  
O shortode recebe um parâmetro: 'id' da submissão.  
A submissão **não** é de fato excluída, mas apenas muda o valor da coluna 'is_active' para 0(zero).
A exclusão efetiva pode ser feita por meio do action hook 'fafar_cf7crud_after_delete', que passa o 'id' da submissão por parâmetro:

```
do_action( 'fafar_cf7crud_after_delete', $id );
```

## Banco de Dados

**fafar_cf7crud_submissions**:

- id             VARCHAR(255) NOT NULL | PRIMARY KEY
- data           JSON NOT NULL
- form_id        VARCHAR(255) NOT NULL
- object_name    VARCHAR(255)
- is_active      VARCHAR(255) NOT NULL DEFAULT '1'
- owner          VARCHAR(255)
- group_owner    VARCHAR(255)
- permissions    VARCHAR(255) NOT NULL DEFAULT '777'
- remote_ip      VARCHAR(255)
- submission_url VARCHAR(255)
- updated_at     TIMESTAMP NOT NULL | DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
- created_at     TIMESTAMP NOT NULL | DEFAULT CURRENT_TIMESTAMP

### Está ativo

Seguindo a filosofia de que deve-se evitar a exclusão, a coluna 'is_active' pode ser útil.  
    
Uso comum:  
'1': Ativado;  
'0': Desativado;  

### Owner, Group Owner e Permissions

Para auxiliar com permissionamento de objetos, o plugin conta as colunas de 'owner', 'group_owner' e 'permissions'. O plugin **não** utiliza nenhuma dessas colunas nem seus valores em nenhum momento, exeto para inserção de seus valores, seja na criação ou na edição, conforme o usuário desejar.  
Para estratégia de permissionamento, aconselha-se a do [Linux](https://www.redhat.com/sysadmin/linux-file-permissions-explained).

#### Owner

Nome ou ID qualquer do dono.

#### Group-Owner

Nome ou ID qualquer do grupo dono.

#### Permissions

Algum código ou esquema de permissionamento.

Exemplo:
0 - Nenhuma permissão de acesso. Equivalente a -rwx.
1 - Permissão de execução (x).
2 - Permissão de gravação (w).
3 - Permissão de gravação e execução (wx).
4 - Permissão de leitura (r).
5 - Permissão de leitura e execução (rx).
6 - Permissão de leitura e gravação (rw).
7 - Permissão de leitura, gravação e execução.

### Remote IP

O plugin gera um hidden input com o IP do cliente.  
O 'name' do input é 'far_db_column_remote_ip'.

### Submission URL

O plugin gera um hidden input com a URL do formulário de submissão.  
O 'name' do input é 'far_db_column_submission_url'.

### Banco de Dados Customizado

Use o filter hook 'z_set_database' para utilizar outro banco de dados.  
Parâmetros:  
$wpdb. WPDB Object. Instância global.

Observação: Se espera uma tabela 'fafar_cf7crud_submissions' com as respectivas colunas no banco de dados no retorno do hook.

## Opções de Formulário

### Entrada

Trata-se das opções de entrada de dados no formulário.  
É possível inserir valores da tabela padrão do plugin('fafar-cf7crud-submissions') com algumas 'options' personalizadas.
Pode-se usar a palavra 'this' para representar o valor da submissão atual.

#### far_crud_display

Essa propriedade define os valores aplicados do HTML. Exemplo:

```html
<option value="PROPRIEDADE_VALOR">PROPRIEDADE_LABEL</option>
```

Sintaxe:

far_crud_display:PROPRIEDADE_LABEL|PROPRIEDADE_VALOR

Essa propriedade aceita apenas um par chave|valor.

Para o preenchimento de dados, essa é a única propriedade obrigatória.

#### far_crud_column_filter

Essa propriedade aplica filtro de igualdade com as colunas|valores informados.

Sintaxe:

far_crud_column_filter:COLUNA_1|VALOR_1:COLUNA_2|VALOR_2

Essa propriedade aceita mais de um par chave|valor.

Atualmente, não é possível inserir uma chave ou valor com espaços.

É possível utilizar a palavra_chave 'this' para se referenciar ao valor da propriedade atual da 'submissão' em questão. Exemplo:

```
[... far_crud_column_filter:id|this:color|this]
```

Para o preenchimento de dados, essa propriedade é opcional.

#### far_crud_json_filter

Essa propriedade aplica filtro de igualdade com as propriedades_json|valores informados.
Essas propriedades são as que ficam armazenadas na coluna 'data'.

Sintaxe:

far_crud_json_filter:PROP_JSON_1|VALOR_1:PROP_JSON_2|VALOR_2

Essa propriedade aceita mais de um par chave|valor.

Atualmente, não é possível inserir uma chave ou valor com espaços.

É possível utilizar a palavra-chave 'this' para se referenciar ao valor da propriedade atual da 'submissão' em questão. Exemplo:

```
[... far_crud_json_filter:id|this:color|this]
```

Para o preenchimento de dados, essa propriedade é opcional.

### far_crud_shortcode
É possível utilizar a propriedade 'far_crud_shortcode' para usar um shortcode como fonte de dados para qualquer campo selecionável.  
O shortcode deve retornar uma string JSON, com chaves e valores, obrigatóriamente, sendo a chave a 'value' da 'option' e valor sendo a label:
```
[select profissional far_crud_shortcode:obter_profissionais_ativos]
```
  
```json
[
    {
        "1234hjk4" : "Jeferson"
    }, 
    {
        "fjk234dskl" : "Linda"
    }
    ...
]
```
  
Sendo:
```html
    <option value="1234hjk4">Jeferson</option>
    <option value="fjk234dskl">Linda</option>
```
Obs.: Apesar do exemplo acima ter sido com o campo 'select', essa propriedade pode ser usada em qualquer campo selecionável.  
  
#### Text To Time
É possível trocar o tipo do campo CF7 do tipo 'text' em 'time', apenas inserir a classe 'far-crud-time-field':
```
[text horas class:far-crud-time-field]
```

#### Text To Datetime
É possível trocar o tipo do campo CF7 do tipo 'text' em 'datetime', apenas inserir a classe 'far-crud-datetime-field':
```
[text dia_hora class:far-crud-datetime-field]
```

#### Text To Datetime Local
É possível trocar o tipo do campo CF7 do tipo 'text' em 'datetime-local', apenas inserir a classe 'far-crud-datetime-local-field':
```
[text dia_hora class:far-crud-datetime-local-field]
```

#### Text Transform
É possível utilizar 4 valores da propriedade css 'text-transform' em campo CF7, apenas inserir a classe 'far-crud-transform-VALOR'.
Os 4 valores suportados são:
```
[text nome class:far-crud-transform-capitalize]
```
```
[text nome class:far-crud-transform-uppercase]
```
```
[text nome class:far-crud-transform-lowercase]
```
```
[text nome class:far-crud-transform-none]
```

### Saída

É possível manipular os valores de todas as colunas da tabela "fafar_cf7crud_submissions" configurando o nome da tag CF7 com a seguinte sintaxe:  
far_db_column_ + NOME_DA_COLUNA  
Exemplos:

```
[text far_db_column_id "1"]
[hidden far_db_column_object_name "carro"]
[select far_db_created_at "1"]
```

### Pular Submissão

Existe a possibilidade de pular a submissão pelo plugin, se for usado os hooks de verificações finais, para isso.
Segue:

```
[hidden far_prevent_submit "1"]
```

### Pular Submissão De Tag's

Além de poder usar o filter hook 'fafar_cf7crud_not_allowed_fields' para pular tag's específicas, é possível realizar o mesmo, ao criar uma tag CF7.  
Exemplo:

```
[text far_ignore_tag_carro "Corsa"]
```
Dessa forma, essa tag será ignorada pelo FAFAR CF7CRUD.

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

### Tratamento Nos Campos

#### fafar_cf7crud_san_lb_

Adicione o prefixo 'fafar_cf7crud_san_lb_' para que quebras de linha sejam mantidas.
Utiliza-se a função [sanitize_textarea_field](https://developer.wordpress.org/reference/functions/sanitize_textarea_field/).
Exemplo:

```
[textarea fafar_cf7crud_san_lb_sobre]
```

#### fafar_cf7crud_san_em_

Adicione o prefixo 'fafar_cf7crud_san_em_' para remover todos os caracteres não permitidos em um e_mail.
Utiliza-se a função [sanitize_email](https://developer.wordpress.org/reference/functions/sanitize_email/).
Exemplo:

```
[email fafar_cf7crud_san_em_email]
```

#### fafar_cf7crud_san_fi_

Adicione o prefixo 'fafar_cf7crud_san_fi_' para substituir todos os espaços em branco por traços.
Utiliza-se a função [sanitize_file_name](https://developer.wordpress.org/reference/functions/sanitize_file_name/).
Exemplo:

```
[text fafar_cf7crud_san_fi_nome_arquivo]
```

#### fafar_cf7crud_san_key_

Adicione o prefixo 'fafar_cf7crud_san_key_' para transformar todas as letras em minúsculos. Além disso, permite apenas sublinhados e traços.
Utiliza-se a função [sanitize_key](https://developer.wordpress.org/reference/functions/sanitize_key/).
Exemplo:

```
[text fafar_cf7crud_san_key_chave]
```

#### Outros

Se nenhum prefixo conhecido for informado, utiliza-se o [sanitize_text_field](https://developer.wordpress.org/reference/functions/sanitize_text_field/).

## Arquivos

Cada input[type=file] se torna uma entrada personalizada para melhor controle, na criação e atualização de formulários.

### Prefixo

No banco de dados, o nome do arquivo é guardado dessa forma:  
'fafar_cf7crud_file_' + NOME DA PROPRIEDADE = NOME DO ARQUIVO.EXTENSÃO  
Exemplo:  
'fafar_cf7crud_file_foto':'foto.jpg'

### Pasta de Upload

Caminho: [...]wp-content/uploads/fafar-cf7crud-uploads/

Use o filter hook 'fafar_cf7crud_set_upload_dir_path' para utilizar outra pasta de upload.  
Parâmetros:  
$path. string. Caminho padrão

Retorno:
#path. string. Caminho padrão

## Validação Final

### Antes Da Submissão

É possível usar o filter hook 'fafar_cf7crud_before_create' para uma última validação, antes da criação.
Parâmetro esperado: Array PHP, com todos os campos e seus respectivos valores.
Retorno esperado: Objeto PHP | null.
Se o retorno for '**null**', cancela-se a submissão com uma mensagem de erro desconhecido.  
Para cancelar a submissão com uma mensagem de erro, retorne um array com um atributo '**error_msg**'. Esse valor deve ser uma string.
Para apenas pular a criação do objeto pelo plugin, retorne um array com um atributo '**far_prevent_submit**' com o valor **_true_**.

O mesmo vale para o filter hook 'fafar_cf7crud_before_update'.

### Depois Da Submissão

É possível usar o action hook 'fafar_cf7crud_after_create' para vericação, alteração, etc.  
Esse hook passa como único parâmetro, o id da 'submission' cadastrada.

O mesmo vale para o action hook 'fafar_cf7crud_after_update'.

## Plugins Não Suportados

- Drag and Drop Multiple File Upload - Contact Form 7

## Instalação

1. Baixe e extraia os arquivos do plugin para um diretório wp-content/plugin.
2. Ative o plugin através da interface de administração do WordPress.
3. Pronto!

## Changelog

### 1.0.0

### 1.0.1
