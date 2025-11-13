<?php

use Symfony\Component\Routing\RouteCollection;

// access the loader's internal scope to trigger deprecation
$loader->callConfigurator(static fn () => [], 'dummy.php', 'dummy.php');

return new RouteCollection();
