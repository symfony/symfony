Translation Component
=====================

Translation provides tools for loading translation files and generating
translated strings from these including support for pluralization.

    use Symfony\Component\Translation\Translator;
    use Symfony\Component\Translation\MessageSelector;
    use Symfony\Component\Translation\Loader\ArrayLoader;

    $translator = new Translator('fr_FR', new MessageSelector());
    $translator->setFallbackLocale('fr');
    $translator->addLoader('array', new ArrayLoader());
    $translator->addResource('array', array(
        'Hello World!' => 'Bonjour',
    ), 'fr');

    echo $translator->trans('Hello World!') . "\n";

Resources
---------

Silex integration:

https://github.com/fabpot/Silex/blob/master/src/Silex/Provider/TranslationServiceProvider.php

Unit tests:

https://github.com/symfony/symfony/tree/master/tests/Symfony/Tests/Component/Translation

Documentation:

http://symfony.com/doc/2.0/book/translation.html
