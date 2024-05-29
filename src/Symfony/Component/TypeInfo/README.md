TypeInfo Component
==================

The TypeInfo component extracts PHP types information.

**This Component is experimental**.
[Experimental features](https://symfony.com/doc/current/contributing/code/experimental.html)
are not covered by Symfony's
[Backward Compatibility Promise](https://symfony.com/doc/current/contributing/code/bc.html).

Getting Started
---------------

```bash
composer require symfony/type-info
composer require phpstan/phpdoc-parser # to support raw string resolving
```

```php
<?php

use Symfony\Component\TypeInfo\Type;
use Symfony\Component\TypeInfo\TypeResolver\TypeResolver;

// Instantiate a new resolver
$typeResolver = TypeResolver::create();

// Then resolve types for any subject
$typeResolver->resolve(new \ReflectionProperty(Dummy::class, 'id')); // returns an "int" Type instance
$typeResolver->resolve('bool'); // returns a "bool" Type instance

// Types can be instantiated thanks to static factories
$type = Type::list(Type::nullable(Type::bool()));

// Type instances have several helper methods
$type->getBaseType() // returns an "array" Type instance
$type->getCollectionKeyType(); // returns an "int" Type instance
$type->getCollectionValueType()->isNullable(); // returns true
```

Resources
---------

 * [Contributing](https://symfony.com/doc/current/contributing/index.html)
 * [Report issues](https://github.com/symfony/symfony/issues) and
   [send Pull Requests](https://github.com/symfony/symfony/pulls)
   in the [main Symfony repository](https://github.com/symfony/symfony)
