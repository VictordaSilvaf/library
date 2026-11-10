# Slim Framework 4 Skeleton Application

![Coverage Status](https://coveralls.io/repos/github/slimphp/Slim-Skeleton/badge.svg?branch=master)

Use this skeleton application to quickly setup and start working on a new Slim Framework 4 application. This application uses the latest Slim 4 with Slim PSR-7 implementation and PHP-DI container implementation. It also uses the Monolog logger.

This skeleton application was built for Composer. This makes setting up a new Slim Framework application quick and easy.

## Install the Application

Run this command from the directory in which you want to install your new Slim Framework application. You will require PHP 7.4 or newer.

```bash
composer create-project slim/slim-skeleton [my-app-name]
```

Replace `[my-app-name]` with the desired directory name for your new application. You'll want to:

* Point your virtual host document root to your new application's `public/` directory.
* Ensure `logs/` is web writable.

To run the application in development, you can run these commands 

```bash
cd [my-app-name]
composer start
```

Or you can use `docker-compose` to run the app with `docker`, so you can run these commands:

```bash
cd [my-app-name]
docker-compose up -d
```

After that, open `http://localhost:8080` in your browser.

Run this command in the application directory to run the test suite

```bash
composer test
```

That's it! Now go build something cool.

## Como criar um módulo completo

O módulo `Ordering` é o modelo. Um módulo chega nesse ponto quando o agregado guarda as regras, o PostgreSQL grava o estado, a HTTP cria, confirma e cancela, e os eventos saem do agregado e chegam num ouvinte. Os testes cobrem o domínio, o repositório e a rota, inclusive uma gravação real na tabela.

Troque `Ordering` pelo nome do novo contexto e siga a ordem. Cada passo só usa o que o passo anterior já deixou pronto.

### 1. Crie as pastas

O namespace `App\` aponta para `src/`.

```text
src/Ordering/                     # Módulo responsável pelos pedidos.
  Domain/                         # Regras e conceitos de negócio.
    Aggregate/                    # Entidades agrupadas para manter a consistência das regras.
    Event/                        # Fatos relevantes ocorridos no domínio, como PedidoCriado.
    Exception/                    # Erros por violação das regras de negócio.
    Repository/                   # Interfaces para buscar e salvar agregados.
    ValueObject/                  # Objetos definidos pelo valor, sem identidade, como Dinheiro.
  Application/                    # Casos de uso que coordenam o domínio.
    Command/                      # Solicitações de ações, como CriarPedido.
    Handler/                      # Executa os casos de uso associados aos comandos.
    DTO/                          # Estruturas para transportar dados entre camadas.
    Event/                        # Eventos e reações que coordenam fluxos da aplicação.
  Infrastructure/                 # Implementações técnicas e integrações externas.
    Persistence/Doctrine/         # Mapeamentos e repositórios implementados com Doctrine.
    Event/                        # Publicação e consumo de eventos usando mecanismos técnicos.
    Http/                         # Controllers, rotas e adaptação de requisições e respostas HTTP.
tests/Ordering/                   # Testes do módulo de pedidos.
  Domain/Aggregate/               # Testes das regras e da consistência dos agregados.
  Application/Handler/            # Testes da execução dos casos de uso.
  Infrastructure/Http/            # Testes dos endpoints e das respostas HTTP.
  Infrastructure/Persistence/     # Testes da persistência e integração com o banco.
  Support/                        # Utilitários, fixtures e fábricas para os testes.
```

### 2. Comece pelos value objects

`OrderId` e `CustomerId` estendem um `Uuid` compartilhado. O construtor só aceita o formato UUID. `random()` gera um id novo. `__toString()` devolve o valor, porque o Doctrine precisa transformar o id em texto.

`Money` fica em `src/Shared/Domain/ValueObject`, porque pedido e catálogo usam o mesmo dinheiro. Ele guarda o valor em centavos, num inteiro. `create('40.50', 'USD')` vira `4050`. `fromCents()` reconstitui esse inteiro na leitura do banco. `add()` e `subtract()` recusam moedas diferentes. Preço zero ou negativo é inválido.

`Product` carrega título, autor e `Money`. `ProductCollection` guarda uma lista de produtos, copia a si mesma em `copy()` e não devolve a lista interna para quem está fora. `OrderStatus` é um enum `pending`, `confirmed` e `cancelled`.

### 3. Escreva o agregado

`Order` é a única entrada para mudar um pedido. O construtor é privado. `Order::create()` recebe o cliente e a coleção de produtos.

Regras que o agregado aplica:

- Uma coleção vazia lança `EmptyOrderException` com a mensagem `Products are required`.
- O total começa no preço do primeiro produto e soma o restante. A moeda do pedido é a moeda dos produtos.
- `create()` guarda uma cópia da coleção. `getProducts()` devolve outra cópia. Alterar a coleção de fora não muda o pedido.
- Só um pedido `pending` pode ser confirmado, cancelado ou receber outro produto. Qualquer outro estado lança `InvalidOrderStateException`.
- `addProduct()` troca a coleção por uma nova. O Doctrine enxerga a mudança porque a propriedade passa a apontar para outro objeto.
- `create()`, `confirm()` e `cancel()` gravam `OrderCreated`, `OrderConfirmed` e `OrderCancelled`. `releaseEvents()` devolve essa lista e esvazia o buffer.

Cubra essas regras em `tests/Ordering/Domain/Aggregate/OrderTest.php` antes de seguir. Rode com `./sail test tests/Ordering/Domain/Aggregate/OrderTest.php`.

### 4. Defina o repositório no domínio

`OrderRepository` é uma interface. Ela declara `save`, `findById`, `findByCustomerId`, `findAll`, `findByStatus` e `delete`. O domínio não conhece SQL nem Doctrine.

`findById` recebe um `OrderId` e devolve `?Order`. Quem implementa a busca usa `$id->getValue()`, a string do UUID, e não o objeto inteiro.

### 5. Modele os comandos

Um comando carrega só o que o caso de uso precisa.

- `CreateOrder` recebe `CustomerId` e `ProductCollection`. A coleção guardada é uma cópia, e `getProducts()` devolve outra.
- `ConfirmOrder` e `CancelOrder` recebem o `OrderId`.

### 6. Implemente os handlers

Cada handler chama o agregado, grava e publica os eventos. A ordem é esta:

```php
$order = Order::create($command->getCustomerId(), $command->getProducts());
$this->orders->save($order);

foreach ($order->releaseEvents() as $event) {
    $this->events->dispatch($event);
}

return OrderOutput::fromOrder($order);
```

`ConfirmOrderHandler` e `CancelOrderHandler` buscam o pedido. Se `findById()` devolver `null`, lançam `OrderNotFoundException`. Essa exceção estende `DomainRecordNotFoundException`, e a action base transforma isso em HTTP 404.

`EventDispatcher` é uma interface da aplicação, com `dispatch(object $event): void`. A implementação `SynchronousEventDispatcher` percorre os ouvintes. `OrderEventLogger` recebe `OrderCreated`, `OrderConfirmed` e `OrderCancelled` e registra cada um no log.

No teste do handler, use um repositório em memória e um ouvinte que guarda os eventos recebidos. Confirme que criar, confirmar e cancelar entregam o evento certo, e que um id inexistente não entrega evento nenhum.

### 7. Descreva a entrada e a saída

`OrderInput::fromArray()` lê o JSON. `customerId` é texto preenchido. `products` é uma lista. Cada item tem `title`, `author`, `price` e `currency`. O preço entra como decimal (`"40.00"`). `toCommand()` monta o `CreateOrder`. Uma lista vazia passa por aqui; a regra do pedido vazio continua no agregado.

`OrderOutput::fromOrder()` descreve `id`, `customerId`, `status`, `total` e `products`. O total e o preço de cada produto saem em centavos: `"40.00"` vira `4000`.

O corpo de `POST /orders` fica assim:

```json
{
  "customerId": "uuid-do-cliente",
  "products": [
    {
      "title": "Domain-Driven Design",
      "author": "Eric Evans",
      "price": "40.00",
      "currency": "USD"
    }
  ]
}
```

### 8. Abra a HTTP

As actions estendem `App\Shared\Infrastructure\Http\Action`. Cada uma lê a requisição, chama o handler e responde com o `OrderOutput`.

- `CreateOrderAction` lê o corpo, monta o comando e responde `201`. `EmptyOrderException` e `InvalidArgumentException` viram `400`.
- `ConfirmOrderAction` e `CancelOrderAction` leem `{id}` da rota e montam o comando. Um UUID inválido vira `400`. Um pedido fora de `pending` também vira `400`.
- Um pedido que não existe vira `404` com a mensagem `Order not found`.

Registre as rotas em `app/routes.php`:

```php
$app->group('/orders', function (Group $group) {
    $group->post('', CreateOrderAction::class);
    $group->post('/{id}/confirm', ConfirmOrderAction::class);
    $group->post('/{id}/cancel', CancelOrderAction::class);
});
```

O teste HTTP sobe a aplicação Slim e troca `OrderRepository` por um repositório em memória. Cubra criar, confirmar, cancelar, pedido sem produtos e id inexistente.

### 9. Suba o PostgreSQL

A imagem do PHP instala `pdo_pgsql`. O `docker-compose.yml` sobe o Postgres 17 com o banco `library`, o usuário `library` e a senha `library`, na porta `5432`. O serviço `php` recebe `DATABASE_HOST`, `DATABASE_PORT`, `DATABASE_NAME`, `DATABASE_USER` e `DATABASE_PASSWORD`, e só inicia depois que o banco responde.

Suba com `./sail up -d`.

### 10. Faça o agregado persistível

`Order` e `Money` recebem atributos do Doctrine. A tabela é `orders`.

| Campo | Como é guardado |
| --- | --- |
| `id` | UUID, tipo `order_id` |
| `customer_id` | UUID, tipo `order_customer_id` |
| `status` | string do enum |
| `products` | JSON, tipo `product_collection` |
| `total_amount`, `total_currency` | `Money` embutido, valor em centavos |
| `created_at`, `updated_at` | `datetime_immutable` |
| `confirmed_at`, `cancelled_at` | `datetime_immutable`, nulos até a transição |

`Money` é um embeddable. `ProductCollection` não leva atributo do Doctrine: o tipo `product_collection` serializa cada produto como `title`, `author`, `amount` e `currency`, e na leitura reconstrói com `Money::fromCents()`. Os tipos `order_id` e `order_customer_id` aceitam o value object ou a string.

O buffer de eventos não entra na tabela. Um método `#[ORM\PostLoad]` zera esse buffer depois que o Doctrine hidrata o pedido.

`DoctrineOrderRepository` implementa `OrderRepository`. `save()` faz `persist` e `flush`. `findById()` chama `find($id->getValue())`. `DoctrineTypes::register()` registra os três tipos.

`EntityManagerFactory` lê as variáveis `DATABASE_*`, registra os tipos e cria a tabela `orders` se ela ainda não existir. O nome da coluna de produtos é `products`.

Ligue no container:

- Em `app/dependencies.php`, `EntityManagerInterface` aponta para `EntityManagerFactory`, e `EventDispatcher` aponta para `SynchronousEventDispatcher` com o `OrderEventLogger`.
- Em `app/repositories.php`, `OrderRepository` aponta para `DoctrineOrderRepository`.

O teste do repositório usa SQLite em memória, cria o schema pelos atributos e confere o ciclo gravar, limpar o entity manager e buscar de novo. Inclua produto em outra moeda, `addProduct` seguido de outro `save`, e confirmação.

### 11. Prove a gravação pela rota

`tests/Ordering/Infrastructure/Http/OrderPersistenceTest.php` sobe a aplicação sem trocar o repositório. O `POST /orders` passa pela action, pelo handler e pelo Doctrine, e o teste lê a linha em `orders`. No `tearDown`, apague esse registro.

Confira status `pending`, `customer_id`, `total_amount` em centavos e `total_currency`.

### 12. Rode a verificação

```bash
./sail test tests/Ordering
./sail php vendor/bin/phpcs src/Ordering tests/Ordering
./sail php vendor/bin/phpstan analyse src/Ordering --no-progress
```

O módulo está completo quando esses três comandos passam e um `POST /orders` deixa uma linha em `orders` com o total em centavos, o status `pending` e os produtos em JSON.
