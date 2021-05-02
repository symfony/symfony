<?php

use Symfony\Config\AcmeConfig\NestedConfig;

return static function (NestedConfig $config) {
    throw new RuntimeException('This code should not be run.');
};
