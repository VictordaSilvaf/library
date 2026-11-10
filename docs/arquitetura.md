# Arquitetura

A aplicação é um Slim 4 com três contextos. `Ordering` e `Catalog` são event-sourced. `User` é uma leitura em memória e não participa desse fluxo. O PostgreSQL guarda o histórico em `domain_events` e duas projeções, `orders` e `catalogs`. A publicação dos eventos acontece fora do request, em `bin/publish-outbox.php`.

O namespace `App\` aponta para `src/`.

## Contextos

| Contexto | Agregado | Linguagem | Persistência |
| --- | --- | --- | --- |
| Ordering | `Order` | `pending` → `confirmed` ou `cancelled` | stream + projeção `orders` |
| Catalog | `Catalog` | `draft` → `published` → `withdrawn` | stream + projeção `catalogs` |
| User | `User` | consulta | `InMemoryUserRepository` |

Pedido e catálogo não compartilham status nem verbos. Confirmar um pedido e publicar um catálogo são fatos diferentes.

## Camadas

A dependência aponta para dentro. Domínio não importa aplicação nem infraestrutura. Shared não importa Ordering nem Catalog.

```text
HTTP Action  →  Command  →  Handler  →  Aggregate
                                  ↓
                            Repository (interface no domínio)
                                  ↓
                     Doctrine*Repository (infraestrutura)
                                  ↓
                     EventStreamWriter + Projector
```

| Pasta | Responsabilidade |
| --- | --- |
| `Domain/Aggregate` | Única porta de mudança. Construtor privado. Estado só muda em `apply()`. |
| `Domain/Event` | Fatos imutáveis. Cada um carrega `eventId` e `occurredAt`. |
| `Domain/ValueObject` | Identidade e medidas. Sem ciclo de vida próprio. |
| `Domain/Repository` | Contrato de gravar e buscar. Não conhece SQL. |
| `Domain/Exception` | Regra violada ou registro ausente. |
| `Application/Command` | Dados de um caso de uso. |
| `Application/Handler` | Carrega o agregado, chama um método, grava. Não despacha evento. |
| `Application/DTO` | JSON de entrada e de saída. |
| `Infrastructure/Http` | Traduz HTTP em comando e exceção de domínio em status. |
| `Infrastructure/Persistence` | Event store, projeção e codecs do contexto. |
| `Infrastructure/Event` | Ouvintes. Hoje só logam. |
| `src/Shared` | O que os dois contextos usam: `Uuid`, `Money`, agregado event-sourced, event store, serializer, relay. |
| `src/Bootstrap/PayloadCodec` | Junta os codecs de Shared, Ordering e Catalog. É a raiz de composição. |
| `app/` | Container, rotas e settings. Não é regra de negócio. |

## Um comando

`POST /orders` percorre este caminho:

1. `CreateOrderAction` lê o JSON e monta `CreateOrder`.
2. `CreateOrderHandler` chama `Order::create()`.
3. O agregado valida, chama `record()` e não mexe no banco.
4. `record()` faz `apply()` (o estado muda), guarda o evento pendente e soma 1 na versão.
5. `DoctrineOrderRepository::save()` pede os eventos pendentes ao `EventStreamWriter`.
6. Na mesma transação, o writer acrescenta o stream e o `OrderProjector` atualiza a linha em `orders`.
7. `save()` devolve os eventos e esvazia o buffer. `published_at` continua nulo.
8. A action responde `201` com `OrderOutput`. Nenhum ouvinte rodou ainda.

Confirmar, cancelar, publicar e retirar seguem o mesmo caminho: buscar, transicionar, gravar.

## Event sourcing

A fonte da verdade é a tabela `domain_events`.

| Coluna | Papel |
| --- | --- |
| `stream_id` | Id do agregado. |
| `version` | Posição no stream. Começa em 1. Chave primária junto com `stream_id`. |
| `event_id` | Identidade do fato. |
| `event_type` | Classe do evento. |
| `payload` | JSON do construtor. |
| `occurred_at` | Instante do fato. |
| `published_at` | Nulo até o relay entregar o evento. |

`findById` lê o stream em ordem e chama `reconstitute()`. `replay()` aplica cada evento e não os recoloca na fila. O objeto que volta da busca tem o buffer vazio.

A versão esperada na gravação é `versão atual - quantidade de eventos pendentes`. Se outro processo já gravou essa posição, `append()` lança `ConcurrentModificationException`.

`Money` guarda centavos. `"40.00"` vira `4000`. `Uuid::random()` devolve a subclasse que chamou (`OrderId`, `CatalogId`, `EventId`).

## Projeção

`orders` e `catalogs` existem para consulta e para achar ids. Não reconstroem o agregado. `findByCustomerId` e `findByStatus` leem o id na projeção e depois reconstituem pelo stream.

O projetor só entende eventos. `OrderCreated` insere a linha. `OrderConfirmed` e `OrderCancelled` atualizam status e data. `OrderProductAdded` acrescenta o produto ao JSON e troca o total. O catálogo segue o mesmo modelo: insert no `CatalogCreated` e um `UPDATE` para cada fato seguinte.

Stream e projeção entram na mesma transação. Se o projetor falha, o evento também não fica gravado.

## Publicação

O request termina com o evento gravado e não publicado. O processo separado é o relay:

```bash
docker compose exec php php bin/publish-outbox.php
docker compose exec php php bin/publish-outbox.php --follow
```

O primeiro publica o que está pendente e sai. O segundo fica lendo.

`OutboxRelay::publishPending()` seleciona `published_at IS NULL`, desserializa o payload, chama o `EventDispatcher` e só então preenche `published_at`. Se o ouvinte lança, a linha continua nula e a próxima execução tenta de novo. A entrega é pelo menos uma vez: um processo que morre depois do dispatch e antes do `UPDATE` publica o mesmo evento outra vez. Os ouvintes de hoje, `OrderingEventLogger` e `CatalogEventLogger`, só escrevem log.

## Serialização

`DomainEventSerializer` não conhece pedido nem catálogo. Ele lê os parâmetros do construtor e pergunta ao `PayloadNormalizerRegistry`.

| Normalizer | Onde vive | O que converte |
| --- | --- | --- |
| `DateTimeNormalizer`, `EnumNormalizer`, `MoneyNormalizer`, `UuidNormalizer` | Shared | tipos dos dois contextos |
| `ProductNormalizer`, `ProductCollectionNormalizer` | Ordering | produto do pedido |
| `SkuNormalizer`, `ImageNormalizer`, `ImageCollectionNormalizer` | Catalog | sku e imagens |

`PayloadCodec::serializer()` registra todos. O container entrega esse serializer aos dois repositórios e ao relay. Um tipo sem normalizer faz o registry lançar.

`StoredEvent` implementa `getEventId()` e `getOccurredAt()` para qualquer evento. O store usa esses métodos, não propriedades dinâmicas.

## Ordering

`Order::create(CustomerId, ProductCollection)` gera o `OrderId` e o instante. Coleção vazia lança `EmptyOrderException` (`Products are required`).

| Método | De | Para | Evento |
| --- | --- | --- | --- |
| `create` | — | `pending` | `OrderCreated` |
| `confirm` | `pending` | `confirmed` | `OrderConfirmed` |
| `cancel` | `pending` | `cancelled` | `OrderCancelled` |
| `addProduct` | `pending` | `pending` | `OrderProductAdded` |

Fora de `pending`, a transição lança `InvalidOrderStateException`. `addProduct` não tem rota HTTP. O total do `OrderProductAdded` já vem somado no evento, e `apply()` usa esse valor.

`POST /orders`

```json
{
  "customerId": "uuid",
  "products": [
    { "title": "Domain-Driven Design", "author": "Eric Evans", "price": "40.00", "currency": "USD" }
  ]
}
```

A resposta traz `total.amount` em centavos (`4000`) e `status: "pending"`.

| Rota | Sucesso | 400 | 404 |
| --- | --- | --- | --- |
| `POST /orders` | 201 | corpo inválido ou pedido vazio | — |
| `POST /orders/{id}/confirm` | 200 | id inválido ou estado inválido | `Order not found` |
| `POST /orders/{id}/cancel` | 200 | id inválido ou estado inválido | `Order not found` |

## Catalog

O id chega no comando. `CreateCatalogHandler` usa o id gerado por `CatalogInput`. Nome e descrição são aparados. Nome até 255 caracteres, descrição até 4000. Preço tem de ser positivo. A coleção de imagens não pode ser vazia. URL só `http` ou `https`.

| Método | De | Para | Evento |
| --- | --- | --- | --- |
| `create` | — | `draft` | `CatalogCreated` |
| `publish` | `draft` | `published` | `CatalogPublished` |
| `withdraw` | `published` | `withdrawn` | `CatalogWithdrawn` |
| `changeName` | não terminal | o mesmo | `CatalogNameChanged` |
| `changeDescription` | não terminal | o mesmo | `CatalogDescriptionChanged` |
| `changePrice` | não terminal | o mesmo | `CatalogPriceChanged` |
| `replaceImages` | não terminal | o mesmo | `CatalogImagesReplaced` |

`withdrawn` é terminal: nome, descrição, preço e imagens não mudam mais. Valor igual ao atual não gera evento. `occurredAt` anterior à última mudança lança `InvalidCatalogStateException`. As quatro mudanças de conteúdo existem no agregado e ainda não têm rota.

`POST /catalogs`

```json
{
  "sku": "book-01",
  "name": "Dune",
  "description": "A novel",
  "price": "40.00",
  "currency": "USD",
  "categoryId": "uuid",
  "images": [{ "url": "https://example.com/dune.jpg" }]
}
```

| Rota | Sucesso | 400 | 404 |
| --- | --- | --- | --- |
| `POST /catalogs` | 201, status `draft` | payload, preço, sku ou imagem inválidos | — |
| `POST /catalogs/{id}/publish` | 200, status `published` | id ou estado inválido | `Catalog not found` |
| `POST /catalogs/{id}/withdraw` | 200, status `withdrawn` | id ou estado inválido | `Catalog not found` |

`DomainRecordNotFoundException` vira 404 na `Action` base. As actions traduzem `EmptyCatalogException`, `InvalidCatalogStateException` e `InvalidArgumentException` em 400.

## User

`GET /users` e `GET /users/{id}` leem `InMemoryUserRepository`. Não há stream, projeção nem evento. `UserNotFoundException` segue o mesmo 404 da action base.

## Onde o container liga as peças

`app/dependencies.php` cria o `EntityManager` (e o schema, via `DoctrineEventStore::ensureSchema`), o dispatcher com os dois loggers, o serializer de `PayloadCodec` e o `OutboxRelay`.

`app/repositories.php` liga `OrderRepository` e `CatalogRepository` às implementações Doctrine, e `UserRepository` à implementação em memória.

Os testes de handler e de HTTP trocam o repositório por um em memória. Esse duplo não reconstitui pelo stream. Os testes `DoctrineOrderRepositoryTest`, `DoctrineCatalogRepositoryTest` e `OutboxRelayTest` usam SQLite e provam reconstituição, conflito de versão e a publicação separada do request. `OrderPersistenceTest` grava no Postgres do Compose.

## Subir e testar

```bash
docker compose up -d
docker compose exec php vendor/bin/phpunit
```

A API fica em `http://localhost:8080`. O Postgres publica a porta `5432`, banco `library`, usuário `library`, senha `library`.
