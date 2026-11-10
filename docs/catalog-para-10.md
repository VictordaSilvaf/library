# Catalog: o que falta para chegar a 10

Referência: o contexto Ordering, que já fecha domínio, aplicação, HTTP, Doctrine e teste. A nota atual é 3. Este arquivo lista o que o Catalog ainda precisa fazer para ficar no mesmo nível, com a linguagem do catálogo (`active`, `inactive`, `archived`, SKU, categoria e imagens), sem copiar pedido.

## Agregado

Arquivo: `src/Catalog/Domain/Aggregate/Catalog.php`.

O construtor é público e `create()`, `confirm()` e `cancel()` só chamam `record()`. O `Order` faz o contrário: construtor privado, fábrica `create()` e transições que mudam estado antes do evento.

- [x] Mover o namespace para `App\Catalog\Domain\Aggregate`. O arquivo está em `Aggregate/`, mas declara `App\Catalog\Domain`. O autoload de `App\` aponta para `src/`, então a classe não carrega. A porta em `CatalogRepository` importa `App\Catalog\Domain\Catalog`, que também não existe.
- [x] Construtor privado. Quem cria o agregado é a fábrica.
- [ ] `Catalog::create(...)` gera `CatalogId`, exige nome, descrição, preço, SKU, categoria e ao menos uma imagem, começa `active`, preenche `createdAt` e `updatedAt`, e grava `CatalogCreated` com o estado já definido.
- [ ] Trocar `confirm()` e `cancel()` por transições do catálogo. `deactivate()` só a partir de `active`. `archive()` a partir de `active` ou `inactive`. `activate()` só a partir de `inactive`. Estado inválido lança exceção de domínio.
- [ ] Cada transição atualiza `status` e `updatedAt`, e só então grava o evento com o status novo. Hoje o evento leva o status antigo.
- [ ] Remover `confirmedAt`, `cancelledAt` e o índice `idx_catalogs_customer_id`. O catálogo não tem cliente. Se a data da transição importar, usar `deactivatedAt` e `archivedAt`.
- [ ] Mapear `price` como `Money` embutido, igual ao total do pedido. O tipo de coluna `money` não existe.
- [ ] Mapear `status` com `enumType: CatalogStatus::class`, como `OrderStatus`.
- [ ] `getImages()` devolve uma cópia. `getProducts()` do pedido faz isso. Hoje a coleção interna escapa.

## Value objects

- [ ] `Image` e `ImageCollection` estão em `App\Catalog\Domain\ValueObject`. Passar para `App\Catalog\Domain\ValueObject`, no mesmo caminho do arquivo.
- [ ] `ImageCollection` e `CatalogCollection` estendem `Ramsey\Collection\Collection` sem chamar o construtor da classe pai, e `ramsey/collection` não está no `composer.json`. Seguir `ProductCollection` e `OrderCollection`: um array interno, `copy()`, `isEmpty()` e `getImages()` / `getCatalogs()`.
- [ ] `CatalogCollection` recusa lista vazia. `findAll()` e `findByStatus()` precisam poder devolver zero itens, como `OrderCollection`.
- [ ] Remover o `use App\Shared\Domain\ValueObject\Uuid` de `CatalogStatus`. O status é enum e não usa esse import.
- [ ] Alinhar o acesso ao SKU com o restante dos identificadores. `Sku::value()` existe; `CatalogId` e `CategoryId` expõem `getValue()`.

## Exceções de domínio

O pedido tem `EmptyOrderException`, `InvalidOrderStateException` e `OrderNotFoundException`. O catálogo não tem nenhuma.

- [ ] `InvalidCatalogStateException` para transição ilegal.
- [ ] `CatalogNotFoundException`, estendendo `DomainRecordNotFoundException`, com a mensagem `Catalog not found`.
- [ ] Exceção para catálogo sem imagem, no mesmo papel de `EmptyOrderException`.

## Eventos

`CatalogCreated`, `CatalogConfirmed` e `CatalogCancelled` já implementam `DomainEvent`, e o `CatalogEventLogger` já está no dispatcher. Falta o evento nascer da transição.

- [ ] `CatalogCreated` sai de `Catalog::create()`, uma vez.
- [ ] Trocar `CatalogConfirmed` e `CatalogCancelled` pelos eventos das transições reais (`CatalogDeactivated`, `CatalogArchived`, `CatalogActivated`), com o status depois da mudança.
- [ ] O logger trata esses eventos novos e deixa de procurar os nomes de pedido.

## Repositório

`CatalogRepository` é só a interface. `DoctrineOrderRepository` dentro de `src/Catalog` continua sendo o repositório de pedido.

- [ ] Corrigir o tipo do agregado na interface para `App\Catalog\Domain\Aggregate\Catalog`.
- [ ] `findById()` devolve `null` quando não existe.
- [ ] `findAll()` e `findByStatus()` devolvem coleção vazia, não `null`.
- [ ] Implementar `DoctrineCatalogRepository` com `save`, `findById`, `findAll`, `delete` e `findByStatus`.
- [ ] Implementar `InMemoryCatalogRepository` para os testes de handler e HTTP, no papel de `InMemoryOrderRepository`.

## Aplicação

Não existe `src/Catalog/Application`.

- [ ] `CreateCatalog`, com cópia da coleção de imagens, como `CreateOrder` copia os produtos.
- [ ] Comandos das transições, no papel de `ConfirmOrder` e `CancelOrder`, carregando o `CatalogId`.
- [ ] `CreateCatalogHandler` cria, salva e despacha os eventos com `App\Shared\Domain\Event\EventDispatcher`.
- [ ] Handlers de transição carregam o agregado, lançam `CatalogNotFoundException` se faltar, aplicam a regra, salvam e despacham.
- [ ] `CatalogInput::fromArray()` valida o corpo e monta o comando. Campos: `sku`, `name`, `description`, `price`, `currency`, `categoryId`, `images`.
- [ ] `CatalogOutput::fromCatalog()` implementa `JsonSerializable` com id, sku, name, description, price, categoryId, status, images e datas.

## HTTP

Estes arquivos estão na pasta do Catalog e ainda são actions de pedido. O namespace é `App\Ordering\Infrastructure\Http`:

- `src/Catalog/Infrastructure/Http/CreateOrderAction.php`
- `src/Catalog/Infrastructure/Http/ConfirmOrderAction.php`
- `src/Catalog/Infrastructure/Http/CancelOrderAction.php`

- [ ] Apagar essas três cópias.
- [ ] `CreateCatalogAction` responde 201. Payload inválido, SKU inválido ou sem imagem vira 400.
- [ ] As actions de transição respondem 200. Agregado ausente vira 404 com `Catalog not found`. Transição ilegal vira 400.
- [ ] Registrar as rotas em `app/routes.php`: `POST /catalogs`, `POST /catalogs/{id}/deactivate`, `POST /catalogs/{id}/archive` e `POST /catalogs/{id}/activate`.

## Persistência

A pasta `src/Catalog/Infrastructure/Persistence` é cópia do Ordering: `DoctrineOrderRepository`, `OrderIdType`, `CustomerIdType`, `ProductCollectionType`, `DoctrineTypes` e `EntityManagerFactory`. O factory do pedido só mapeia `src/Ordering/Domain` e só cria a tabela `orders`.

- [ ] Apagar essa cópia.
- [ ] Incluir `src/Catalog/Domain` no `AttributeDriver` do `EntityManagerFactory` que já sobe o banco, para o mesmo `EntityManager` enxergar os dois contextos.
- [ ] Criar a tabela `catalogs` quando ela ainda não existir.
- [ ] Tipos Doctrine do catálogo: `catalog_id`, `category_id` e `image_collection`. Registrar em `DoctrineTypes`.
- [ ] SKU como string de 64. Status como enum do Doctrine. Preço como `Money` embutido, com prefixo de coluna.

## Testes

O pedido cobre agregado, handler, HTTP e persistência. O catálogo não tem teste.

- [ ] `tests/Catalog/Domain/Aggregate/CatalogTest.php`: criação `active`, rejeição sem imagem, deactivate, archive, activate e rejeição de transição inválida. Cada operação libera um único evento e a segunda chamada de `releaseEvents()` volta vazia. `getImages()` não altera a coleção interna.
- [ ] `tests/Catalog/Application/Handler/CatalogHandlerTest.php`: cada handler despacha o evento certo. Transição de id inexistente não despacha nada e lança `CatalogNotFoundException`.
- [ ] `tests/Catalog/Infrastructure/Http/CatalogActionTest.php`: 201 na criação, 400 sem imagem, 200 na transição e 404 quando o catálogo não existe.
- [ ] `tests/Catalog/Infrastructure/Persistence/DoctrineCatalogRepositoryTest.php`: salva, relê pelo id e devolve `null` para id ausente.
- [ ] `tests/Catalog/Infrastructure/Http/CatalogPersistenceTest.php`: `POST /catalogs` grava a linha, no papel de `OrderPersistenceTest`.

## Definição de pronto

A nota 10 é este conjunto fechado:

- o agregado só nasce pela fábrica e só muda de status pelas transições válidas;
- os value objects carregam pelo autoload e a coleção de consulta aceita vazio;
- o repositório Doctrine implementa a porta e o `EntityManager` mapeia o Catalog;
- HTTP, comando e handler cobrem criar, ativar, desativar e arquivar, com 400 e 404;
- a suíte acima passa junto com os 51 testes que já existem.
