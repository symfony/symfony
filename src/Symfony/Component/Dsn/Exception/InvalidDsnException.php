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
 * Base exception when DSN is not valid.
 *
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 */
class InvalidDsnException extends \InvalidArgumentException
{
    private $dsn;

    public function __construct(string $dsn, string $message)
    {
        $this->dsn = $dsn;
        parent::__construct(sprintf('%s. (%s)', $message, $dsn));
    }

    public function getDsn(): string
    {
        return $this->dsn;
    }
}
