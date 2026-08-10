<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\PropertyInfo\Exception;

class MappingException extends \RuntimeException implements ExceptionInterface
{
    /**
     * @param list<string> $invalidMethods
     */
    public function __construct(
        string $message,
        public readonly string $forClass,
        public readonly array $invalidMethods,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }
}
