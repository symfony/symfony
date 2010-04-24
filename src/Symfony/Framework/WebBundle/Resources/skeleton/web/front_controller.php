<?php

require_once __DIR__.'/../{{ application }}/{{ class }}Kernel.php';

$kernel = new {{ class }}Kernel('prod', false);
$kernel->handle()->send();
