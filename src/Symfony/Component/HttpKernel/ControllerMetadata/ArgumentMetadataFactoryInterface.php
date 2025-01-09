<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\ControllerMetadata;

use Symfony\Component\ArgumentResolver\ArgumentMetadata\ArgumentMetadataFactoryInterface as BaseArgumentMetadataFactoryInterface;

/**
 * Builds method argument data.
 *
 * @author Iltar van der Berg <kjarli@gmail.com>
 *
 * @deprecated
 */
interface ArgumentMetadataFactoryInterface extends BaseArgumentMetadataFactoryInterface
{
    /**
     * @return ArgumentMetadata[]
     */
    public function createArgumentMetadata(string|object|array $controller, ?\ReflectionFunctionAbstract $reflector = null): array;
}
