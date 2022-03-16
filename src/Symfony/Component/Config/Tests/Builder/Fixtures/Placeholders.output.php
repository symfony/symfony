<?php

return [
    'enabled' => '%env(bool:FOO_ENABLED)%',
    'favorite_float' => '%eulers_number%',
    'good_integers' => '%env(json:MY_INTEGERS)%',
];
