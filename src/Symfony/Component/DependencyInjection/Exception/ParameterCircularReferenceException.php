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
 * This exception is thrown when a circular reference in a parameter is detected.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class ParameterCircularReferenceException extends RuntimeException
{
    public function __construct(
        private array $parameters,
        ?\Throwable $previous = null,
    ) {
        parent::__construct(\sprintf('Circular reference detected for parameter "%s" ("%s" > "%s").', $parameters[0], implode('" > "', $parameters), $parameters[0]), 0, $previous);
    }

    public function getParameters(): array
    {
        return $this->parameters;
    }
}
