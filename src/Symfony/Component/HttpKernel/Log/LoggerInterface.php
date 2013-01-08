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

use Psr\Log\LoggerInterface as PsrLogger;

/**
 * LoggerInterface.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @api
 */
interface LoggerInterface extends PsrLogger
{
    /**
     * @api
     */
    public function emerg($message, array $context = array());

    /**
     * @api
     */
    public function crit($message, array $context = array());

    /**
     * @api
     */
    public function err($message, array $context = array());

    /**
     * @api
     */
    public function warn($message, array $context = array());
}
