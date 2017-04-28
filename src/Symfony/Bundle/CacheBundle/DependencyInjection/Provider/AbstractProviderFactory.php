<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\CacheBundle\DependencyInjection\Provider;

use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * @author Victor Berchet <victor@suumit.com>
 */
abstract class AbstractProviderFactory implements ProviderFactoryInterface
{
    private $providers = array();

    public function configure(ContainerBuilder $container, $name, array $config)
    {
        $signature = $this->getSignature($container, $config);
        $type = $this->getName();

        if (isset($this->providers[$type][$signature])) {
            $container->setAlias($this->getId($name), $this->providers[$type][$signature]);
        } else {
            $definition = $this
                ->getDefinition($config)
                ->addMethodCall('setNamespace', array($config['namespace']))
                ->setPublic(true)
            ;
            $container->setDefinition($provider = $this->getId($name), $definition);
            $this->providers[$type][$signature] = $provider;
        }
    }

    public function getName()
    {
        $class = get_class($this);
        $pos = strrpos($class, '\\');
        $class = false === $pos ? $class :  substr($class, $pos + 1);

        if ('ProviderFactory' !== substr($class, -15)) {
            throw new \LogicException('The factory name could not be determined.');
        }

        return strtolower(substr($class,0, -15));
    }

    protected function getId($name)
    {
        return 'cache.provider.concrete.'.$name;
    }


}