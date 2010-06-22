<?php

namespace Symfony\Framework\PropelBundle;

use Symfony\Foundation\Bundle\Bundle;
use Symfony\Components\DependencyInjection\ContainerInterface;
use Symfony\Components\DependencyInjection\Loader\Loader;
use Symfony\Components\DependencyInjection\Loader\XmlFileLoader;
use Symfony\Framework\PropelBundle\DependencyInjection\PropelExtension;

class PropelBundle extends Bundle
{
    public function buildContainer(ContainerInterface $container)
    {
        Loader::registerExtension(new PropelExtension());
    }

    public function boot(ContainerInterface $container)
    {
        require_once $container->getParameter('propel.path').'/runtime/lib/Propel.php';

        if (0 === strncasecmp(PHP_SAPI, 'cli', 3)) {
            set_include_path($container->getParameter('propel.phing_path').'/classes'.PATH_SEPARATOR.get_include_path());
        }

        $container->getPropelService();
    }
}
