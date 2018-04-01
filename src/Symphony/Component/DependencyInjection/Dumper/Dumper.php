<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\DependencyInjection\Dumper;

use Symphony\Component\DependencyInjection\ContainerBuilder;

/**
 * Dumper is the abstract class for all built-in dumpers.
 *
 * @author Fabien Potencier <fabien@symphony.com>
 */
abstract class Dumper implements DumperInterface
{
    protected $container;

    public function __construct(ContainerBuilder $container)
    {
        $this->container = $container;
    }
}
