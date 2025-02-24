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
 * Interface for containers which know how to find the definition of services.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
interface DefinitionFinderContainerInterface extends ContainerInterface
{
    /**
     * Returns definition for a service id.
     *
     * @param string $id The service id
     */
    public function findDefinition(string $id): Definition;

}
