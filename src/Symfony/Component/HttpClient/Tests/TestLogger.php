<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpClient\Tests;

use Psr\Log\AbstractLogger;

class TestLogger extends AbstractLogger
{
    public array $logs = [];

    public function log($level, $message, array $context = []): void
    {
        $this->logs[] = preg_replace_callback('!\{([^\}\s]++)\}!', static fn ($m) => $context[$m[1]] ?? $m[0], $message);
    }
}
