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
 * Thrown when you cannot use functions in a DSN.
 *
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 */
class FunctionsNotAllowedException extends InvalidDsnException
{
    public function __construct(string $dsn)
    {
        parent::__construct($dsn, 'Function are not allowed in this DSN');
    }
}
