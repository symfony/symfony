<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Log;

use Symfony\Component\HttpKernel\Log\LoggerInterface;

/**
 * NullLogger.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class NullLogger implements LoggerInterface
{
    public function emerg($message) {}

    public function alert($message) {}

    public function crit($message) {}

    public function err($message) {}

    public function warn($message) {}

    public function notice($message) {}

    public function info($message) {}

    public function debug($message) {}
}
