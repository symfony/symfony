<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Configuration;

/**
 * This interface must be implemented by nodes which can be used as prototypes.
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
interface PrototypeNodeInterface extends NodeInterface
{
    /**
     * Sets the name of the node.
     *
     * @param string $name The name of the node
     * @return void
     */
    function setName($name);
}