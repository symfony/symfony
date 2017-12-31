<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Config\Resource;

/**
 * Resource checker for the ResourceInterface. Exists for BC.
 *
 * @author Matthias Pigulla <mp@webfactory.de>
 *
 * @deprecated since 2.8, to be removed in 3.0.
 */
class BCResourceInterfaceChecker extends SelfCheckingResourceChecker
{
    public function supports(ResourceInterface $metadata)
    {
        /* As all resources must be instanceof ResourceInterface,
           we support them all. */
        return true;
    }

    public function isFresh(ResourceInterface $resource, $timestamp)
    {
        @trigger_error(sprintf('The class "%s" is performing resource checking through ResourceInterface::isFresh(), which is deprecated since Symfony 2.8 and will be removed in 3.0', get_class($resource)), E_USER_DEPRECATED);

        return parent::isFresh($resource, $timestamp); // For now, $metadata features the isFresh() method, so off we go (quack quack)
    }
}
