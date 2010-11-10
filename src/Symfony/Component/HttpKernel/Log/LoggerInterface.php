<?php

namespace Symfony\Component\HttpKernel\Log;

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

/**
 * LoggerInterface.
 *
 * @author Fabien Potencier <fabien.potencier@symfony-project.com>
 */
interface LoggerInterface
{
    function log($message, $priority);

    function emerg($message);

    function alert($message);

    function crit($message);

    function err($message);

    function warn($message);

    function notice($message);

    function info($message);

    function debug($message);
}
