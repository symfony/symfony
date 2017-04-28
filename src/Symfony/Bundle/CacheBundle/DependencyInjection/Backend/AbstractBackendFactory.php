<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\CacheBundle\DependencyInjection\Backend;

use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * @author Victor Berchet <victor@suumit.com>
 */
abstract class AbstractBackendFactory implements BackendFactoryInterface
{
    public function init(ContainerBuilder $container, $config)
    {
    }

    public function getType()
    {
        return strtolower($this->getName());
    }

    public function getConfigKey()
    {
        return strtolower(preg_replace(
            array('/[^a-z0-9.-_]/i', '/(?<=[a-zA-Z0-9])[A-Z]/'),
            array('', '_\\0'),
            $this->getName())
        );
    }

    protected function getName()
    {
        $class = get_class($this);
        $pos = strrpos($class, '\\');
        $class = false === $pos ? $class :  substr($class, $pos + 1);

        if ('BackendFactory' !== substr($class, -14)) {
            throw new \LogicException('The factory name could not be determined.');
        }

        return substr($class,0, -14);
    }
}