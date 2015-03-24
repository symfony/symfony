EventDispatcher Component
=========================

The Symfony EventDispatcher component implements the Mediator pattern in a
simple and effective way to make your projects truly extensible.

```php
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\Event;

$dispatcher = new EventDispatcher();

$dispatcher->addListener('event_name', function (Event $event) {
    // ...
});

$dispatcher->dispatch('event_name');
```

Resources
---------

You can run the unit tests with the following command:

    $ cd path/to/Symfony/Component/EventDispatcher/
    $ composer install
    $ phpunit
