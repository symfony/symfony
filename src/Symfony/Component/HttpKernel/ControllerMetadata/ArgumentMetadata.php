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

use Symfony\Component\ArgumentResolver\ArgumentMetadata\ArgumentMetadata as BaseArgumentMetadata;

/**
 * Responsible for storing metadata of an argument.
 *
 * @author Iltar van der Berg <kjarli@gmail.com>
 *
 * @deprecated
 */
class ArgumentMetadata extends BaseArgumentMetadata
{
    public function getControllerName(): string
    {
        return $this->getCallableName();
    }

    public static function fromBaseArgumentMetadata(BaseArgumentMetadata $metadata)
    {
        return new self(
            $metadata->getName(),
            $metadata->getType(),
            $metadata->isVariadic(),
            $metadata->hasDefaultValue(),
            $metadata->hasDefaultValue() ? $metadata->getDefaultValue() : null,
            $metadata->isNullable(),
            $metadata->getAttributes(),
            $metadata->getCallableName(),
        );
    }
}
