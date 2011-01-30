<?php

namespace Symfony\Component\DependencyInjection\Configuration;

/**
 * This interface must be implemented by nodes which can be used as prototypes.
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
interface PrototypeNodeInterface extends NodeInterface
{
    function setName($name);
}