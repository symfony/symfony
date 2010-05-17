<?php

namespace Symfony\Framework\PropelBundle;

use Symfony\Foundation\Bundle\Bundle as BaseBundle;
use Symfony\Components\DependencyInjection\ContainerInterface;
use Symfony\Components\DependencyInjection\Loader\Loader;
use Symfony\Components\DependencyInjection\Loader\XmlFileLoader;
use Symfony\Framework\PropelBundle\DependencyInjection\PropelExtension;

class Bundle extends BaseBundle
{
    public function buildContainer(ContainerInterface $container)
    {
        Loader::registerExtension(new PropelExtension());
    }
}
