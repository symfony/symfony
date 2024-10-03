<?php

/** @var \Symfony\Component\Routing\Loader\PhpFileLoader $loader */
/** @var \Symfony\Component\Routing\RouteCollection $collection */
$collection = $loader->import('validpattern.php');
$collection->addDefaults([
    'foo' => 123,
]);
$collection->addRequirements([
    'foo' => '\d+',
]);
$collection->addOptions([
    'foo' => 'bar',
]);
$collection->setCondition('context.getMethod() == "POST"');
$collection->addPrefix('/prefix');

return $collection;
