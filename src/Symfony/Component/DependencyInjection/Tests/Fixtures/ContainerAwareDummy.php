<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Tests\Fixtures;

use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerAwareTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * @deprecated since Symfony 6.4, to be removed in 7.0
 */
class ContainerAwareDummy implements ContainerAwareInterface
{
    use ContainerAwareTrait;

    public function getContainer(): ?ContainerInterface
    {
        return $this->container;
    }
}

