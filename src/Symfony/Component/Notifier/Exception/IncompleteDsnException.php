<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Exception;

/**
 * @author Fabien Potencier <fabien@symfony.com>
 */
class IncompleteDsnException extends InvalidArgumentException
{
    public function __construct(
        string $message,
        private ?string $dsn = null,
        ?\Throwable $previous = null,
    ) {
        if ($dsn) {
            $message = sprintf('Invalid "%s" notifier DSN: %s', $dsn, $message);
        }

        parent::__construct($message, 0, $previous);
    }

    public function getOriginalDsn(): string
    {
        return $this->dsn;
    }
}
