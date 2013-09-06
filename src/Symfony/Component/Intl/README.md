Intl Component
=============

A PHP replacement layer for the C intl extension that includes additional data
from the ICU library.

The replacement layer is limited to the locale "en". If you want to use other
locales, you should [install the intl extension] [0] instead.

Documentation
-------------

The documentation for the component can be found [online] [1].

Resources
---------

You can run the unit tests with the following command:

    $ cd path/to/Symfony/Component/Intl/
    $ composer.phar install --dev
    $ phpunit

[0]: http://www.php.net/manual/en/intl.setup.php
[1]: http://symfony.com/doc/2.4/components/intl.html
