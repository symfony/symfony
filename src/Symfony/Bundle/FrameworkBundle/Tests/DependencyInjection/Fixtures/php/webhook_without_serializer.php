<?php

$container->loadFromExtension('framework', [
    'webhook' => ['enabled' => true],
    'http_client' => ['enabled' => true],
    'serializer' => ['enabled' => false],
]);
