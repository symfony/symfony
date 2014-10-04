<?php

use Symfony\Component\Routing\Loader\PhpFileLoader;
/** @var PhpFileLoader $loader */
if (!isset($loader)) {
    throw new RuntimeException('Variable $loader should be defined in loading file!');
}
return $loader->load(__DIR__.'/validpattern.php');