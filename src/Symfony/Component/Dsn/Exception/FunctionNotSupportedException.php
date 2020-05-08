<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Symfony\Component\Dsn\Exception;

/**
 * Thrown when the provided function is not supported.
 *
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 */
class FunctionNotSupportedException extends InvalidDsnException
{
    private $function;

    public function __construct(string $dsn, string $function, ?string $message = null)
    {
        parent::__construct($dsn, $message ?? sprintf('Function "%s" is not supported', $function));
        $this->function = $function;
    }

    public function getFunction(): string
    {
        return $this->function;
    }
}
