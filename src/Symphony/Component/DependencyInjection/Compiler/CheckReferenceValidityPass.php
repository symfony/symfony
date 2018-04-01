<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\DependencyInjection\Compiler;

use Symphony\Component\DependencyInjection\Definition;
use Symphony\Component\DependencyInjection\Reference;
use Symphony\Component\DependencyInjection\Exception\RuntimeException;

/**
 * Checks the validity of references.
 *
 * The following checks are performed by this pass:
 * - target definitions are not abstract
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
class CheckReferenceValidityPass extends AbstractRecursivePass
{
    protected function processValue($value, $isRoot = false)
    {
        if ($isRoot && $value instanceof Definition && ($value->isSynthetic() || $value->isAbstract())) {
            return $value;
        }
        if ($value instanceof Reference && $this->container->hasDefinition((string) $value)) {
            $targetDefinition = $this->container->getDefinition((string) $value);

            if ($targetDefinition->isAbstract()) {
                throw new RuntimeException(sprintf(
                    'The definition "%s" has a reference to an abstract definition "%s". '
                   .'Abstract definitions cannot be the target of references.',
                   $this->currentId,
                   $value
                ));
            }
        }

        return parent::processValue($value, $isRoot);
    }
}
