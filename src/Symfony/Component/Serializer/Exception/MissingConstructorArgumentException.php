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

class MissingConstructorArgumentException extends MissingConstructorArgumentsException
{
    private string $class;
    private string $missingArgument;

    /**
     * @param class-string $class
     */
    public function __construct(string $class, string $missingArgument, int $code = 0, \Throwable $previous = null)
    {
        $this->class = $class;
        $this->missingArgument = $missingArgument;

        $message = sprintf('Cannot create an instance of "%s" from serialized data because its constructor requires parameter "%s" to be present.', $class, $missingArgument);

        parent::__construct($message, $code, $previous, [$missingArgument]);
    }

    public function getClass(): string
    {
        return $this->class;
    }

    public function getMissingArgument(): string
    {
        return $this->missingArgument;
    }
}
