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
    public function emerg($message, array $context = array()) {}

    public function alert($message, array $context = array()) {}

    public function crit($message, array $context = array()) {}

    public function err($message, array $context = array()) {}

    public function warn($message, array $context = array()) {}

    public function notice($message, array $context = array()) {}

    public function info($message, array $context = array()) {}

    public function debug($message, array $context = array()) {}
}
