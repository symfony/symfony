<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Serializer\Exception;

/**
 * @author Maxime VEBER <maxime.veber@nekland.fr>
 */
class MissingConstructorArgumentsException extends RuntimeException
{
    /**
     * @param string[]          $missingArguments
     * @param class-string|null $class
     */
    public function __construct(
        string $message,
        int $code = 0,
        ?\Throwable $previous = null,
        private array $missingArguments = [],
        private ?string $class = null,
    ) {
        parent::__construct($message, $code, $previous);
    }

    /**
     * @return string[]
     */
    public function getMissingConstructorArguments(): array
    {
        return $this->missingArguments;
    }

    /**
     * @return class-string|null
     */
    public function getClass(): ?string
    {
        return $this->class;
    }
}
