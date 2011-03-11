<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Component\DependencyInjection;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;

class CallableExtension implements ExtensionInterface
{
    private $callable;
    private $alias;

    public function __construct($callable, $alias)
    {
        $this->callable = $callable;
        $this->alias = $alias;
    }

    public function load(array $configs, ContainerBuilder $container)
    {
        call_user_func($this->callable, $configs, $container);
    }

    public function getAlias()
    {
        return $this->alias;
    }

    public function getNamespace()
    {
        return false;
    }

    public function getXsdValidationBasePath()
    {
        return false;
    }
}
