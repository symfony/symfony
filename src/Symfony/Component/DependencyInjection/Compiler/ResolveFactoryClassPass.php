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

use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Exception\RuntimeException;

/**
 * @author Maxime Steinhausser <maxime.steinhausser@gmail.com>
 */
class ResolveFactoryClassPass extends AbstractRecursivePass
{
    protected bool $skipScalars = true;

    protected function processValue(mixed $value, bool $isRoot = false): mixed
    {
        if ($value instanceof Definition && \is_array($factory = $value->getFactory()) && null === $factory[0]) {
            if (null === $class = $value->getClass()) {
                throw new RuntimeException(sprintf('The "%s" service is defined to be created by a factory, but is missing the factory class. Did you forget to define the factory or service class?', $this->currentId));
            }

            $factory[0] = $class;
            $value->setFactory($factory);
        }

        return parent::processValue($value, $isRoot);
    }
}
