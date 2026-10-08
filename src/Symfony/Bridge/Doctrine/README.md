Doctrine Bridge
===============

The Doctrine bridge provides integration for
[Doctrine](http://www.doctrine-project.org/) with various Symfony components.

EntityExists form guessing
-------------------------

`EntityExistsTypeGuesser` can infer a `ChoiceType` field from an `EntityExists` constraint with an explicit, non-identifier `identifierField`. Register it deliberately in applications where all eligible inferred fields have bounded, non-sensitive values:

```yaml
# config/services.yaml
services:
    Symfony\Bridge\Doctrine\Form\EntityExistsTypeGuesser:
        arguments: ['@doctrine', '@validator.mapping.class_metadata_factory']
        tags: ['form.type_guesser']
```

```php
use Symfony\Bridge\Doctrine\Validator\Constraints\EntityExists;

final class AssignOrderCommand
{
    #[EntityExists(entityClass: Product::class, identifierField: 'sku')]
    public ?string $productSku = null;
}

// With AssignOrderCommand as the form's data class:
$builder->add('productSku');
```

The field lists distinct, non-null values ordered by the referenced field, preserving conditions and limits from the repository's query builder. Empty strings are omitted. Supported values include scalars, dates, intervals, bridge UUIDs and ULIDs, and backed enums mapped to a single string or integer column, provided the form property's write target accepts their PHP type and null. Associations, identifier lookups, repository methods, ambiguous repeated constraints, array and binary columns, and text LOB columns are not guessed.

The guess takes precedence over the standard Doctrine, validator and enum guesses. Set the form type explicitly for sensitive fields or large datasets. There is no implicit limit: rendering enumerates all distinct choices, and ordinary `ChoiceType` processing can also load them during submission or when building an expanded field. Each field keeps its own loaded list; repository conditions and SQL filters apply when the list is first loaded. Changing filters after loading does not refresh that list.

Submitted values must belong to this list, including its repository scope, even when `EntityExists` would accept them or its validation group is disabled. Invalid choices use `ChoiceType`'s error message; successfully transformed values still undergo normal constraint validation. Dates, UIDs and enums are returned as typed choices. Submissions must use the rendered choice value; database-equivalent spellings do not bypass choice membership. An edit value absent from the list selects nothing, as with `EntityType`.

An explicit form type bypasses guessing. To replace inferred choices while keeping the guessed type, set `choices` and `choice_loader => null`; override `choice_label` or `choice_value` as needed. For example:

```php
$builder->add('productSku', null, [
    'choices' => ['Featured product' => 'SKU-1'],
    'choice_loader' => null,
    'choice_label' => null,
]);
```

Sponsor
-------

This package is looking for a [backer][1].

Help Symfony by [sponsoring][3] its development!

Resources
---------

 * [Contributing](https://symfony.com/doc/current/contributing/index.html)
 * [Report issues](https://github.com/symfony/symfony/issues) and
   [send Pull Requests](https://github.com/symfony/symfony/pulls)
   in the [main Symfony repository](https://github.com/symfony/symfony)

[1]: https://symfony.com/backers
[3]: https://symfony.com/sponsor
