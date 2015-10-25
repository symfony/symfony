<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Doctrine;

use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerAwareTrait;
use Doctrine\Common\Persistence\AbstractManagerRegistry;

/**
 * References Doctrine connections and entity/document managers.
 *
 * @author  Lukas Kahwe Smith <smith@pooteeweet.org>
 */
abstract class ManagerRegistry extends AbstractManagerRegistry implements ContainerAwareInterface
{
    use ContainerAwareTrait;

    /**
     * {@inheritdoc}
     */
    protected function getService($name)
    {
        return $this->container->get($name);
    }

    /**
     * {@inheritdoc}
     */
    protected function resetService($name)
    {
        $this->container->set($name, null);
    }
}
