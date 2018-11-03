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

use Symfony\Contracts\Service\ResetInterface;

/**
 * ResettableContainerInterface defines additional resetting functionality
 * for containers, allowing to release shared services when the container is
 * not needed anymore.
 *
 * @author Christophe Coevoet <stof@notk.org>
 *
 * @deprecated since Symfony 4.2, use "Symfony\Contracts\Service\ResetInterface" instead.
 */
interface ResettableContainerInterface extends ContainerInterface, ResetInterface
{
    /**
     * Resets shared services from the container.
     *
     * The container is not intended to be used again after being reset in a normal workflow. This method is
     * meant as a way to release references for ref-counting.
     * A subsequent call to ContainerInterface::get will recreate a new instance of the shared service.
     */
    public function reset();
}
