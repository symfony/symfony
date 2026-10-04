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
#[BlindIndexed('email', 'user-email')]
private string $emailIndex = '';
```

Its second argument names the index, which is also what the tags are derived
under, so two columns holding an address have an index each.

The query side is unchanged, since it has no entity to read the attribute on:

```php
$repository->findOneBy(['emailIndex' => $index->of($email)]);
```

The listener is given the indexes of the application keyed by that same name:

```php
use Doctrine\ORM\Events;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\KeyManagement\BlindIndex;
use Symfony\Component\KeyManagement\BlindIndex\Projection\Email;
use Symfony\Component\KeyManagement\Bridge\DoctrineOrm\EventListener\BlindIndexListener;

$eventManager->addEventListener(Events::onFlush, new BlindIndexListener(new ServiceLocator([
    'user-email' => static fn () => new BlindIndex($kms, $wrappedKey, 'user-email', new Email()),
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

Every index service is tagged `key_management.blind_index` by autoconfiguration,
the listener is wired on `onFlush` and removed when the application registers no
index, and the data key table joins the schema `doctrine:schema:update` and the
migrations diff against. Only the name is left to state, in the tag, which hands
it to the `$name` argument of the index:

```yaml
services:
    app.index.user_email:
        class: Symfony\Component\KeyManagement\BlindIndex
        arguments:
            $kms: '@key_management.app'
            $wrappedKey: !service
                class: Symfony\Component\KeyManagement\Ciphertext
                arguments: ['%env(base64:APP_INDEX_KEY)%', 'app-key']
            $projection: !service { class: Symfony\Component\KeyManagement\BlindIndex\Projection\Email }
        tags:
            - { name: key_management.blind_index, index: 'user-email' }
```

`APP_INDEX_KEY` holds the `wrapped` value `key-management:generate-data-key app-key`
prints.

Resources
---------

 * [Documentation](https://symfony.com/doc/current/components/key-management.html)
 * [Contributing](https://symfony.com/doc/current/contributing/index.html)
 * [Report issues](https://github.com/symfony/symfony/issues) and
   [send Pull Requests](https://github.com/symfony/symfony/pulls)
   in the [main Symfony repository](https://github.com/symfony/symfony)
