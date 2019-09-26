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

use Symfony\Component\DependencyInjection\Argument\TaggedVariadicArguments;

/**
 * Resolves all TaggedIteratorArgument arguments.
 *
 * @author Grégoire Pineau <lyrixx@lyrixx.info>
 */
class ResolveTaggedVariadicArgumentPass extends AbstractRecursivePass
{
    use PriorityTaggedServiceTrait;

    /**
     * {@inheritdoc}
     */
    protected function processValue($value, $isRoot = false)
    {
        if (!$value instanceof TaggedVariadicArguments) {
            return parent::processValue($value, $isRoot);
        }

        $value->setValues($this->findAndSortTaggedServices($value->getTag(), $this->container));

        return $value;
    }
}
