Cache Component
===============

The cache component provides mechanisms to handle common cache tasks:
- Attach metadata to cached objects
- Attach tags and retrieve objects by tag

The cache component can use any PSR cache compliant library as backend.

You can run the tests with the following command:

    $ cd path/to/Symfony/Component/Cache/
    $ composer.phar install --dev
    $ phpunit
