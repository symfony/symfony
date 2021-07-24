<?php

require __DIR__.'/autoload.php';

return function (array $context): void {
    echo 'Hello World ', $context['SOME_VAR'];
};
