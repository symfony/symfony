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
 * @author Christian Flothmann <christian.flothmann@sensiolabs.de>
 *
 * @internal
 */
trait ChildDefinitionTrait
{
    private function createChildDefinition($parentId)
    {
        if (class_exists(ChildDefinition::class)) {
            return new ChildDefinition($parentId);
        }

        return new DefinitionDecorator($parentId);
    }
}
