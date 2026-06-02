# PHP Template Engine

🚀 Motor de Template PHP com Sintaxe HTML-Like JSX em PascalCase

**Sem dependências externas - 100% PHP puro!**

## Características

✅ Sintaxe HTML-like com tags em PascalCase  
✅ Herança de layouts com `extends` e `<Block>`  
✅ Importação de componentes estilo ES6  
✅ Componentes reutilizáveis com props dinâmicas  
✅ Slots para conteúdo dinâmico  
✅ Condicionals: `<If>`, `<ElseIf>`, `<Else>`, `<Unless>`, `<Switch>`  
✅ Loops: `<For>`, `<Foreach>`, `<Forelse>` com `<Break>` e `<Continue>`  
✅ Loop Helper (`__loop.index`, `__loop.total`, etc)  
✅ Variáveis locais com `<Set>`  
✅ Expressões PHP completas em `{{ }}`  
✅ Filtros encadeáveis  
✅ Include de partials  
✅ Atributos dinâmicos  
✅ Auto-escape por padrão + raw output  
✅ Compilação em PHP otimizado  
✅ Cache agressivo  
✅ Debugging helpers  

## Instalação Rápida

```php
<?php
require_once 'autoload.php';

use Core\View\Engine;

$engine = new Engine([
    'templates_dir' => __DIR__ . '/templates',
    'cache_dir' => __DIR__ . '/cache',
    'auto_escape' => true,
    'debug' => true, // false em produção para mensagens seguras
]);

echo $engine->render('home.html', [
    'user' => ['name' => 'João', 'logged' => true],
    'items' => []
]);
```

## Exemplo de Template

```html
extends "layouts/base.html";

import { Button } from "@components/Button";
import { UserCard } from "@components/UserCard";

<Block name="title">Home</Block>

<Block name="content">
  <If condition="{{ user.logged }}">
    <div class="welcome">
      <h1>Bem-vindo, {{ user.name | uppercase }}</h1>
      
      <Button 
        link="/logout"
        text={{ user.button.text ?? "Sair" }}
        color={{ user.button.color ?? "danger" }}
      />
      
      <Foreach items={{ users }} as="user,index">
        <UserCard user={{ user }} index={{ index }} />
      </Foreach>
    </div>
  <Else>
    <div class="login">
      <h1>Faça login para entrar</h1>
    </div>
  </Else>
  </If>
</Block>
```

## Documentação

Veja [SYNTAX.md](./SYNTAX.md) para documentação completa da sintaxe.

## Modo Debug e Produção

- `debug: true` (padrão): mantém mensagens detalhadas para facilitar desenvolvimento.
- `debug: false` ou `environment: 'production'`: retorna mensagens seguras e genéricas para produção.
- Erros de sintaxe compilada geram `SyntaxException` com contexto interno (arquivo, linha, coluna e snippet), preservado no encadeamento da exceção.
- Em templates com `extends`, as exceções de léxico/parser apontam o arquivo original (ex.: `layouts/base.html`) com linha e coluna exatas.

## Estrutura do Projeto

```
src/Core/View/
├── Engine.php                 # Orquestrador principal
├── Compiler.php               # Compila templates
├── Parser.php                 # Parser da AST
├── Lexer.php                  # Tokenizador
├── Renderer.php               # Renderizador
├── Environment.php            # Configuração
├── Nodes/                     # AST Nodes
├── Directives/                # Processadores de tags
├── Filters/                   # Filtros
├── Components/                # Sistema de componentes
├── Cache/                     # Cache
└── Exceptions/                # Exceções
```

## Contribuindo

Contribuições são bem-vindas!

## License

MIT License
