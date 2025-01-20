TypeInfo Component
==================

The TypeInfo component extracts PHP types information.

Getting Started
---------------

```bash
composer require symfony/type-info
composer require phpstan/phpdoc-parser # to support raw string resolving
```

```php
<?php

use Symfony\Component\TypeInfo\Type;
use Symfony\Component\TypeInfo\TypeIdentifier;
use Symfony\Component\TypeInfo\TypeResolver\TypeResolver;

// Instantiate a new resolver
$typeResolver = TypeResolver::create();

// Then resolve types for any subject
$typeResolver->resolve(new \ReflectionProperty(Dummy::class, 'id')); // returns an "int" Type instance
$typeResolver->resolve('bool'); // returns a "bool" Type instance

// Types can be instantiated thanks to static factories
$type = Type::list(Type::nullable(Type::bool()));

// Type classes have their specific methods
Type::object(FooClass::class)->getClassName();
Type::enum(FooEnum::class, Type::int())->getBackingType();
Type::list(Type::int())->isList();

// Every type can be cast to string
(string) Type::generic(Type::object(Collection::class), Type::int()) // returns "Collection<int>"

// You can check that a type (or one of its wrapped/composed parts) is identified by one of some identifiers.
$type->isIdentifiedBy(Foo::class, Bar::class);
$type->isIdentifiedBy(TypeIdentifier::OBJECT);
$type->isIdentifiedBy('float');

// You can also check that a type satifies specific conditions
$type->isSatisfiedBy(fn (Type $type): bool => !$type->isNullable() && $type->isIdentifiedBy(TypeIdentifier::INT));
```

Resources
---------
 * [Documentation](https://symfony.com/doc/current/components/type_info.html)
 * [Contributing](https://symfony.com/doc/current/contributing/index.html)
 * [Report issues](https://github.com/symfony/symfony/issues) and
   [send Pull Requests](https://github.com/symfony/symfony/pulls)
   in the [main Symfony repository](https://github.com/symfony/symfony)
