<?php

namespace Symfony\Bundle\PropelBundle;

use Symfony\Framework\Bundle\Bundle;
use Symfony\Components\DependencyInjection\ContainerInterface;

class PropelBundle extends Bundle
{
    public function boot(ContainerInterface $container)
    {
        require_once $container->getParameter('propel.path').'/runtime/lib/Propel.php';

        if (0 === strncasecmp(PHP_SAPI, 'cli', 3)) {
            set_include_path($container->getParameter('propel.phing_path').'/classes'.PATH_SEPARATOR.get_include_path());
        }

        $container->getPropelService();
    }
}
