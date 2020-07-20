<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Exception;

/**
 * Thrown when trying to inject a parameter into a constructor/method with an incompatible type.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 * @author Julien Maulny <jmaulny@darkmira.fr>
 */
class InvalidParameterTypeException extends InvalidArgumentException
{
    public function __construct(string $serviceId, string $type, \ReflectionParameter $parameter)
    {
        $acceptedType = $parameter->getType();
        $acceptedType = $acceptedType instanceof \ReflectionNamedType ? $acceptedType->getName() : (string) $acceptedType;
        $this->code = $type;

        parent::__construct(sprintf('Invalid definition for service "%s": argument %d of "%s::%s" accepts "%s", "%s" passed.', $serviceId, 1 + $parameter->getPosition(), $parameter->getDeclaringClass()->getName(), $parameter->getDeclaringFunction()->getName(), $acceptedType, $type));
    }
}
