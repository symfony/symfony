# MapEntityCollection

`MapEntityCollection` is an attribute for a Symfony controller argument that automatically builds a Doctrine query for an entity collection based on incoming parameters.

By default, the result is returned as `Doctrine\ORM\Tools\Pagination\Paginator`, but you can also return a plain array.

## Basic Usage

```php
use App\Entity\Product;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Symfony\Bridge\Doctrine\Attribute\MapEntityCollection;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/products', methods: ['GET'])]
public function list(
    #[MapEntityCollection(
        class: Product::class,
        defaultOrdering: ['createdAt' => MapEntityCollection::ORDERING_DESC],
    )]
    Paginator $products,
): Response {
    // ...
}
```

## Example with Query DTO

```php
use App\Entity\Product;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Symfony\Bridge\Doctrine\ArgumentResolver\EntityCollectionAsset\MappingType;
use Symfony\Bridge\Doctrine\Attribute\MapEntityCollection;

final class ProductListQuery
{
    public ?string $status = null;
    public ?int $limit = 20;
    public ?int $page = 1;
}

#[Route('/products', methods: ['GET'])]
public function list(
    ProductListQuery $query,
    #[MapEntityCollection(
        class: Product::class,
        queryString: 'query',
        queryMapping: [
            'limit' => MappingType::LIMIT,
            'page' => MappingType::PAGE,
        ],
    )]
    Paginator $products,
): Response {
    // status will be mapped to where ecr.status = :status
    // limit/page will be used for pagination
}
```

## Attribute Parameters

### `class`

`class-string` of the target Doctrine entity for which the `QueryBuilder` is created.

### `queryString`

The name of a controller argument (usually a DTO) whose properties are used for filtering and pagination.

If `null`, DTO properties are not processed.

### `queryMapping`

`array<string, string>` with rules for DTO property handling.

- `MappingType::IGNORE` - ignore the property.
- `MappingType::LIMIT` - sets `setMaxResults(...)`.
- `MappingType::OFFSET` - sets `setFirstResult(...)`.
- `MappingType::PAGE` - together with `LIMIT`, computes offset as `(page - 1) * limit`.
- if the key exists but the value is not a special mapping type, it behaves like a regular filter field.
- if the key does not exist, the property is treated as an entity field and a `=`/`IN` condition is added.

### `doctrineParameters`

`array<string, mixed>` for predefined query conditions (applied before custom filters).

Supported values:

- scalar/array values (`=` or `IN`);
- `MappingType::NULL` (`IS NULL`);
- `MappingType::NOT_NULL` (`IS NOT NULL`);
- a string key of a request attribute (value is taken from `$request->attributes`);
- `Symfony\Component\ExpressionLanguage\Expression` (evaluated via ExpressionLanguage, `user` is available).

### `filters`

`array<class-string<Symfony\Bridge\Doctrine\ArgumentResolver\EntityCollectionAsset\DoctrineFilterInterface>>` with a list of filter services.

Each filter receives `QueryBuilder`, the current attribute, `Request`, and the object from `queryString`, and can modify the query as needed.

### `defaultOrdering`

`array<string, 'ASC'|'DESC'>` with default sorting.

Applied only when no ordering was already added earlier (for example, by filters).

### `returnPaginator`

- `true` (default): return `Paginator`;
- `false`: execute the query and return an `array` of results.

### `nameConverter`

Optional `NameConverterInterface` for converting field names in `defaultOrdering` (for example, from request camelCase to entity snake_case).

## Notes

- `MapEntityCollection` is attached to a controller argument and is resolved internally by `Symfony\Bridge\Doctrine\ArgumentResolver\EntityCollectionResolver`.
- Sorting direction constants:
  - `MapEntityCollection::ORDERING_ASC`
  - `MapEntityCollection::ORDERING_DESC`
