# Sintaxe do Template Engine

## 1. Extends e Blocos

### Extends

Herança de layout base:

```html
extends "layouts/base.html";
```

### Block

Definição de blocos que podem ser sobrescritos:

```html
<!-- Em layouts/base.html -->
<Block name="title">Título Padrão</Block>
<Block name="content">Conteúdo padrão</Block>

<!-- Em pages/home.html -->
extends "layouts/base.html";

<Block name="title">Home</Block>
<Block name="content">
  Seu conteúdo aqui
</Block>
```

## 2. Imports e Componentes

### Import

Importação de componentes estilo ES6:

```html
import { Button } from "@components/Button";
import { UserCard, Card } from "@components/Cards";
import { Modal } from "@components/Modal";
```

### Componente

Definição de componente:

```html
<!-- components/Button.html -->
<Component name="Button" props="{{
  link: '#',
  text: 'Click',
  color: 'primary',
  disabled: false
}}">
  <a href="{{ props.link }}" class="btn btn-{{ props.color }}">
    <If condition="{{ props.disabled }}">disabled</If>
    {{ props.text }}
  </a>
</Component>
```

Uso do componente:

```html
<Button 
  link="/logout"
  text="Sair"
  color="danger"
/>

<!-- Com props dinâmicas -->
<Button 
  link={{ user.logout_url }}
  text={{ user.button.text }}
  color={{ user.button.color ?? "primary" }}
  disabled={{ !user.verified }}
/>
```

## 3. Condicionals

### If / ElseIf / Else

```html
<If condition="{{ user.role === 'admin' }}">
  <AdminPanel />
<ElseIf condition="{{ user.role === 'moderator' }}">
  <ModeratorPanel />
<Else>
  <UserPanel />
</Else>
</If>
```

### Unless

Inverso do If:

```html
<Unless condition="{{ user.verified }}">
  <Alert message="Verifique seu email" type="warning" />
</Unless>
```

### Switch / Case

```html
<Switch on="{{ user.status }}">
  <Case value="active">
    <StatusBadge color="green" text="Ativo" />
  </Case>
  <Case value="inactive">
    <StatusBadge color="gray" text="Inativo" />
  </Case>
  <Default>
    <StatusBadge color="red" text="Desconhecido" />
  </Default>
</Switch>
```

## 4. Loops

### For

Loop com contador:

```html
<For init="i = 0" condition="i < 10" increment="i++">
  <div>Item {{ i }}</div>
</For>
```

### Foreach

Iteração simples:

```html
<Foreach items={{ users }} as="user">
  <div>{{ user.name }}</div>
</Foreach>
```

Com índice:

```html
<Foreach items={{ users }} as="user,index">
  <div>#{{ index }}: {{ user.name }}</div>
</Foreach>
```

Com chave e valor (arrays associativos):

```html
<Foreach items={{ config }} as="value,key">
  <div>{{ key }}: {{ value }}</div>
</Foreach>
```

### Forelse

Foreach com fallback quando vazio:

```html
<Forelse items={{ users }} as="user">
  <UserCard user={{ user }} />
<Empty>
  <p>Nenhum usuário encontrado</p>
</Empty>
</Forelse>
```

### Break e Continue

```html
<Foreach items={{ items }} as="item">
  <If condition="{{ item.skip }}">
    <Continue />
  </If>
  <If condition="{{ item.stop }}">
    <Break />
  </If>
  <div>{{ item.name }}</div>
</Foreach>
```

### Loop Helper

Variável mágica `__loop` com informações de iteração:

```html
<Foreach items={{ items }} as="item">
  <div>
    <!-- Índice da iteração (começa em 0) -->
    Item #{{ __loop.index }}
    
    <!-- Contagem (começa em 1) -->
    Item {{ __loop.count }}
    
    <!-- Total de items -->
    de {{ __loop.total }}
    
    <!-- Porcentagem de progresso -->
    Progresso: {{ __loop.percentage }}%
    
    <!-- Booleanos -->
    <If condition="{{ __loop.first }}">Primeiro!</If>
    <If condition="{{ __loop.last }}">Último!</If>
    <If condition="{{ __loop.even }}">Número par</If>
    <If condition="{{ __loop.odd }}">Número ímpar</If>
  </div>
</Foreach>
```

## 5. Variáveis Locais

### Set

Declaração de variáveis locais:

```html
<Set var="userName" value={{ user.name | uppercase }} />
<Set var="userAge" value={{ user.age }} />
<Set var="isAdmin" value={{ user.role === 'admin' }} />

<h1>{{ userName }}</h1>
<p>Idade: {{ userAge }}</p>

<If condition="{{ isAdmin }}">
  <AdminPanel />
</If>
```

Variáveis com expressões complexas:

```html
<Set var="userData" value={{
  name: user.name,
  email: user.email,
  isActive: user.status === 'active'
}} />

{{ userData.name }}
{{ userData.email }}
```

## 6. Expressões e Interpolação

### Interpolação Simples

```html
<h1>{{ user.name }}</h1>
<p>{{ product.price }}</p>
```

### Acesso a Arrays

```html
<!-- Array indexado -->
<p>{{ items[0] }}</p>

<!-- Array associativo -->
<p>{{ user['profile'] }}</p>
<p>{{ user.profile['bio'] }}</p>
```

### Chamada de Método

```html
<p>{{ user.getFullName() }}</p>
<p>{{ user.getName(true) }}</p>
```

### Expressões Matemáticas

```html
<p>Total: {{ item.price * item.quantity }}</p>
<p>Resultado: {{ value + 10 }}</p>
```

### Ternário

```html
<p>{{ user.age > 18 ? 'Maior de idade' : 'Menor' }}</p>
```

### Null Coalescing

```html
<p>{{ user.bio ?? 'Sem biografia' }}</p>
<p>{{ user.name ?? user.email ?? 'Anônimo' }}</p>
```

### Expressões Booleanas

```html
<p>{{ user.isActive && user.verified ? 'Ativo' : 'Inativo' }}</p>
<p>{{ user.isAdmin || user.isModerator ? 'Moderador' : 'Usuário' }}</p>
```

## 7. Filtros

### String Filters

```html
{{ text | uppercase }}          <!-- MAIÚSCULAS -->
{{ text | lowercase }}          <!-- minúsculas -->
{{ text | ucfirst }}            <!-- Primeira Letra Maiúscula -->
{{ text | reverse }}            <!-- otxet -->
{{ text | trim }}               <!-- Remove espaços -->
```

### Truncate

```html
{{ text | truncate(50) }}       <!-- Trunca em 50 caracteres -->
{{ text | truncate(50, "...") }} <!-- Com sufixo customizado -->
```

### Slug

```html
{{ "Hello World" | slug }}      <!-- hello-world -->
```

### Number Filters

```html
{{ price | currency("BRL") }}   <!-- R$ 1.234,56 -->
{{ price | currency("USD") }}   <!-- $1,234.56 -->
{{ number | number_format(2) }} <!-- 1.234,56 -->
```

### Date Filters

```html
{{ date | date("d/m/Y") }}          <!-- 27/05/2024 -->
{{ date | date("l, j/n/Y") }}       <!-- Tuesday, 27/5/2024 -->
```

### Array Filters

```html
{{ items | count }}             <!-- Total de items -->
{{ items | first }}             <!-- Primeiro item -->
{{ items | last }}              <!-- Último item -->
{{ items | join(", ") }}        <!-- Implode com separador -->
```

### JSON

```html
{{ data | json }}               <!-- JSON formatado -->
```

### Escape / Raw

```html
{{ text | escape }}             <!-- &lt;script&gt; -->
{{ html | raw }}                <!-- Sem escape -->
```

### Filtros Encadeados

```html
{{ text | lowercase | truncate(30) | uppercase }}
{{ price | currency("BRL") | raw }}
```

## 8. Includes e Partials

### Include Simples

```html
<Include path="@components/header" />
```

### Include com Dados

```html
<Include path="@components/user-card" data={{ { user: currentUser } }} />
```

### Include em Loop

```html
<Foreach items={{ items }} as="item">
  <Include path="@components/item-row" data={{ { item: item, index: __loop.index } }} />
</Foreach>
```

## 9. Raw Output e Segurança

### Auto-Escaped (Padrão)

```html
<!-- Entrada: <script>alert('xss')</script> -->
{{ userInput }}  <!-- Output: &lt;script&gt;alert('xss')&lt;/script&gt; -->
```

### Raw HTML (Sem Escape)

```html
{{ html | raw }}
{! html !}
```

### Escape Manual

```html
{{ text | escape }}
```

## 10. Debugging

### Dump

Exibe valor formatado:

```html
<Dump value={{ user }} />
```

### Log

Log em console/arquivo:

```html
<Log message="Debug: user logged in" />
```
