Translation Component
=====================

Translation provides tools for loading translation files and generating
translated strings from these including support for pluralization.

```php
use Symfony\Component\Translation\Translator;
use Symfony\Component\Translation\MessageSelector;
use Symfony\Component\Translation\Loader\ArrayLoader;

$resourceCatalogue = new ResourceMessageCatalogueProvider();
$resourceCatalogue->addLoader('array', new ArrayLoader());
$resourceCatalogue->addResource('array', array(
    'Hello World!' => 'Bonjour',
), 'fr');

$translator = new Translator('fr', $resourceCatalogue);
echo $translator->trans('Hello World!')."\n";
```

Resources
---------

Silex integration:

https://github.com/silexphp/Silex/blob/master/src/Silex/Provider/TranslationServiceProvider.php

Documentation:

https://symfony.com/doc/2.8/book/translation.html

You can run the unit tests with the following command:

    $ cd path/to/Symfony/Component/Translation/
    $ composer install
    $ phpunit
