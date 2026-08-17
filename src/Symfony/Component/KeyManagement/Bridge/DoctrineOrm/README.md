Symfony Doctrine ORM Key Management Bridge
==========================================

Connects `symfony/key-management` to the Doctrine ORM: a `#[BlindIndexed]` attribute
whose listener fills the blind index of a property on every flush, and a schema
listener declaring the table the data key store of
`symfony/doctrine-dbal-key-management` needs.

**This Bridge is experimental**.
[Experimental features](https://symfony.com/doc/current/contributing/code/experimental.html)
are not covered by Symfony's
[Backward Compatibility Promise](https://symfony.com/doc/current/contributing/code/bc.html).

Blind indexes
-------------

An encrypted column cannot be searched, so the application keeps a keyed tag of
the value in a sibling column and looks the row up by that. Writing the tag is
mechanical and easy to forget, and a row whose tag was not written is a row no
search will ever return. The attribute says, on the column holding the tag,
where the tag comes from:

```php
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\KeyManagement\Bridge\DoctrineOrm\Attribute\BlindIndexed;

#[ORM\Column(type: 'encrypted_string')]
private string $email = '';

#[ORM\Column(length: 64)]
#[BlindIndexed('email', Email::class)]
private string $emailIndex = '';
```

The query side is unchanged, since it has no entity to read the attribute on:

```php
$repository->findOneBy(['emailIndex' => $index->of($email)]);
```

The listener is given the blind indexes of the application, keyed by the class
the attribute names:

```php
use Doctrine\ORM\Events;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\KeyManagement\Bridge\DoctrineOrm\EventListener\BlindIndexListener;

$eventManager->addEventListener(Events::onFlush, new BlindIndexListener(new ServiceLocator([
    Email::class => static fn (): Email => new Email($kms, $wrappedKey),
])));
```

It runs on `onFlush` rather than on `prePersist`, which is dispatched when
`persist()` is called and would have missed a value set afterwards.

Four things it does not do, each of which leaves a tag that does not match its
value: it covers the write path only, it covers the ORM only, so a row inserted
through DBAL or a bulk `UPDATE` leaves the tag as it was, it only sees a
property it can read as a string, and it cannot be a DBAL type, since a type
converts one property into one column and this writes a second one.

With the FrameworkBundle
------------------------

Nothing has to be registered by hand: every `BlindIndex` service is tagged
`key_management.blind_index` by autoconfiguration, the listener is wired on
`onFlush` and removed when the application registers no index, and the data key
table joins the schema `doctrine:schema:update` and the migrations diff against.

```yaml
services:
    App\Security\Email:
        arguments: ['@key_management.app', '%env(APP_INDEX_KEY)%']
```

Resources
---------

 * [Documentation](https://symfony.com/doc/current/components/key-management.html)
 * [Contributing](https://symfony.com/doc/current/contributing/index.html)
 * [Report issues](https://github.com/symfony/symfony/issues) and
   [send Pull Requests](https://github.com/symfony/symfony/pulls)
   in the [main Symfony repository](https://github.com/symfony/symfony)
