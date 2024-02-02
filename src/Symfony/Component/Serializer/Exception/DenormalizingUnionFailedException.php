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

class DenormalizingUnionFailedException extends \RuntimeException
{
    private array $exceptions;

    public function __construct(string $message, array $exceptions)
    {
        $this->exceptions = $exceptions;
        $first = $exceptions[array_key_first($exceptions)] ?? null;
        parent::__construct($message, 0, $first);
    }

    /**
     * @return \Throwable[]
     */
    public function getExceptions(): array
    {
        return $this->exceptions;
    }
}
