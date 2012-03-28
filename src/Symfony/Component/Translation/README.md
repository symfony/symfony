Translation Component
=====================

Translation provides tools for loading translation files and generating
translated strings from these including support for pluralization.

    use Symfony\Component\Translation\Translator;
    use Symfony\Component\Translation\MessageSelector;
    use Symfony\Component\Translation\Loader\ArrayLoader;

    $translator = new Translator('fr_FR', new MessageSelector());
    $translator->setFallbackLocale('fr');
    $translator->addLoader('array', return new ArrayLoader());
    $translator->addResource('array', array(
        'Hello World!' => 'Bonjour',
    ), 'fr');

    $translator->trans('Hello World!');

Resources
---------

Silex integration:

https://github.com/fabpot/Silex/blob/master/src/Silex/Provider/TranslationServiceProvider.php

Documentation:

http://symfony.com/doc/2.0/book/translation.html

You can run the unit tests with the following command:

    phpunit -c src/Symfony/Component/Translation/

If you also want to run the unit tests that depend on other Symfony
Components, declare the following environment variables before running
PHPUnit:

    export SYMFONY_CONFIG=../path/to/Config
    export SYMFONY_YAML=../path/to/Yaml
