<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection;

/**
 * ContainerAwareInterface should be implemented by classes that depends on a Container.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @deprecated since Symfony 6.4, use dependency injection instead
 */
interface ContainerAwareInterface
{
    /**
     * Sets the container.
     *
     * @return void
     */
    public function setContainer(?ContainerInterface $container);
}
