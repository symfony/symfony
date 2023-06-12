Compile https://github.com/arnaud-lb/php-src/tree/lazy-objects

Checkout https://github.com/nicolas-grekas/doctrine-orm/tree/native-proxy

Checkout https://github.com/nicolas-grekas/symfony/tree/native-lazy

Run composer install.

Symlink symfony into doctrine:
```sh
/path/to/symfony/link /path/to/doctrine/orm/
```

Run the tests from doctrine's directory:
```sh
ORM_PROXY_IMPLEMENTATION=lazy-ghost ~/Code/php-src/sapi/cli/php -dmemory_limit=-1 ./vendor/bin/phpunit
```

Patch also vendor/doctrine/persistence/src/Persistence/Reflection/RuntimeReflectionProperty.php like this:
```php
    public function setValue($object, $value = null)
    {
        \ReflectionLazyObject::fromObject($object)?->skipProperty($this->name, $this->class);

        parent::setValue($object, $value);
    }
```
