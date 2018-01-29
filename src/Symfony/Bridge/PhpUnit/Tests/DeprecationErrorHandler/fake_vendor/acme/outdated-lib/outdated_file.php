<?php
/* We have not caught up on the deprecations yet and still call the other lib
   in a deprecated way. */

include __DIR__.'/../lib/SomeService.php';
$defraculator = new \acme\lib\SomeService();
$defraculator->deprecatedApi();
