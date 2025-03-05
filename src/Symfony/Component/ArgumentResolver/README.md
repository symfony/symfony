ArgumentResolver Component
========================

The ArgumentResolver component provides a system that resolves the arguments of a callable based on their metadata and a given input source (e.g. an HTTP Request) at runtime.

```php
<?php

use Symfony\Component\ArgumentResolver\ArgumentResolver;

enum OrderStatus {
    case PLACED = 'placed';
    case CONFIRMED = 'confirmed';
    case IN_TRANSIT = 'transit';
    case SHIPPED = 'shipped';
    case DELIVERED = 'delivered';
}

final class ChangeOrderStatus
{
    public function __invoke(
        int $orderId,
        OrderRepositoryInterface $OrderStatus $newStatus,
        ?\DateTimeInterface $deliveryDate = null
    ) {
        // ...
    }
}

$arguments = (new ArgumentResolver)->getArguments($ca);



```

Getting Started
---------------

```bash
composer require symfony/argument-resolver
```

```php
```

Resources
---------

 * [Documentation](https://symfony.com/doc/current/argument-resolver.html)
 * [Contributing](https://symfony.com/doc/current/contributing/index.html)
 * [Report issues](https://github.com/symfony/symfony/issues) and
   [send Pull Requests](https://github.com/symfony/symfony/pulls)
   in the [main Symfony repository](https://github.com/symfony/symfony)
