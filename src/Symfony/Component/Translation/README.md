Translation Component
=====================

Translation provides tools for loading translation files and generating
translated strings from these including support for pluralization.

```php
use Symfony\Component\Translation\Translator;
use Symfony\Component\Translation\MessageSelector;
use Symfony\Component\Translation\Loader\ArrayLoader;

$translator = new Translator('fr_FR', new MessageSelector());
$translator->setFallbackLocales(array('fr'));
$translator->addLoader('array', new ArrayLoader());
$translator->addResource('array', array(
    'Hello World!' => 'Bonjour',
), 'fr');

echo $translator->trans('Hello World!')."\n";
```

Resources
---------

Silex integration:

https://github.com/fabpot/Silex/blob/master/src/Silex/Provider/TranslationServiceProvider.php

Documentation:

<<<<<<< HEAD
http://symfony.com/doc/2.7/book/translation.html
=======
http://symfony.com/doc/3.0/book/translation.html
>>>>>>> 22cd78c4a87e94b59ad313d11b99acb50aa17b8d

You can run the unit tests with the following command:

    $ cd path/to/Symfony/Component/Translation/
    $ composer install
    $ phpunit
