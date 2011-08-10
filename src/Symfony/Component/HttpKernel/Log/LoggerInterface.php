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

/**
 * LoggerInterface.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @api
 */
interface LoggerInterface
{
    /**
     * @api
     */
    function emerg($message, array $context = array());

    /**
     * @api
     */
    function alert($message, array $context = array());

    /**
     * @api
     */
    function crit($message, array $context = array());

    /**
     * @api
     */
    function err($message, array $context = array());

    /**
     * @api
     */
    function warn($message, array $context = array());

    /**
     * @api
     */
    function notice($message, array $context = array());

    /**
     * @api
     */
    function info($message, array $context = array());

    /**
     * @api
     */
    function debug($message, array $context = array());
}
