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
 * Thrown when trying to inject a parameter into a constructor/method
 * with a type that does not match type hint.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 * @author Julien Maulny <jmaulny@darkmira.fr>
 */
class InvalidParameterTypeHintException extends InvalidArgumentException
{
    public function __construct(string $serviceId, string $typeHint, \ReflectionParameter $parameter)
    {
        parent::__construct(sprintf(
            'Invalid definition for service "%s": argument %d of "%s::%s" requires a "%s", "%s" passed.', $serviceId, $parameter->getPosition(), $parameter->getDeclaringClass()->getName(), $parameter->getDeclaringFunction()->getName(), $parameter->getType()->getName(), $typeHint));
    }
}
