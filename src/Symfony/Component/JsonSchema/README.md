JsonSchema Component
====================

The JsonSchema component generates JSON Schema documents from PHP types, for
API documentation (OpenAPI) and for describing tool inputs and outputs to AI
agents.

**This Component is experimental**.
[Experimental features](https://symfony.com/doc/current/contributing/code/experimental.html)
are not covered by Symfony's
[Backward Compatibility Promise](https://symfony.com/doc/current/contributing/code/bc.html).

Getting Started
---------------

```bash
composer require symfony/json-schema
```

```php
use Symfony\Component\JsonSchema\Configuration;
use Symfony\Component\JsonSchema\Dialect;
use Symfony\Component\JsonSchema\SchemaDocument;
use Symfony\Component\JsonSchema\SchemaGenerator;
use Symfony\Component\TypeInfo\Type;

$generator = SchemaGenerator::create();

// {"$schema": "...", "$ref": "#/$defs/Book", "$defs": {"Book": {...}, "Author": {...}}}
$schema = $generator->generate(Type::object(Book::class));

// an OpenAPI 3.1 request body, restricted to the "book:write" serialization group
$schema = $generator->generate(Type::object(Book::class), new Configuration(
    dialect: Dialect::openApi31(),
    groups: ['book:write'],
));
$schema->getRoot();        // ['$ref' => '#/components/schemas/Book-book.write']
$schema->getDefinitions(); // ['Book-book.write' => [...]]

// the schemas of one OpenAPI document, sharing their definitions
$document = new SchemaDocument();
$bookSchema = $generator->generate(Type::object(Book::class), new Configuration(Dialect::openApi31()), $document);
$authorSchema = $generator->generate(Type::object(Author::class), new Configuration(Dialect::openApi31()), $document);
$document->getDefinitions(); // ['Book' => [...], 'Author' => [...]]

// a self-contained schema without any reference, e.g. for an MCP tool
$schema = $generator->generate(Type::object(Book::class))->flatten();

// the input of a tool, described by the parameters of its method
$schema = $generator->generateForParameters((new \ReflectionMethod(SearchBooks::class, '__invoke'))->getParameters());

echo json_encode($schema);
```

Resources
---------

 * [Contributing](https://symfony.com/doc/current/contributing/index.html)
 * [Report issues](https://github.com/symfony/symfony/issues) and
   [send Pull Requests](https://github.com/symfony/symfony/pulls)
   in the [main Symfony repository](https://github.com/symfony/symfony)
