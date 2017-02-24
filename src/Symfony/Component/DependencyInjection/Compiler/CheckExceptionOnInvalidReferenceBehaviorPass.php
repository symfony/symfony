<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Checks that all references are pointing to a valid service.
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
class CheckExceptionOnInvalidReferenceBehaviorPass extends AbstractRecursivePass
{
    protected function processValue($value, $isRoot = false)
    {
        if ($value instanceof Reference && ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE === $value->getInvalidBehavior()) {
            $destId = (string) $value;

            if (!$this->container->has($destId)) {
                throw new ServiceNotFoundException($destId, $this->currentId);
            }
        }

        return parent::processValue($value, $isRoot);
    }
}
