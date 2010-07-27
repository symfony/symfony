<?php

require_once __DIR__.'/{{ class }}Kernel.php';

use Symfony\Framework\Cache\Cache;

class {{ class }}Cache extends Cache
{
    protected function getOptions()
    {
        return array();
    }
}
