<?php

use Symfony\Config\VariableTypeConfig;

return static function (VariableTypeConfig $config) {
    $config->anyValue('foobar');
};
