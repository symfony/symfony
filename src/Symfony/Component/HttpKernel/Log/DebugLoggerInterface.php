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
 * DebugLoggerInterface.
 *
 * @author Fabien Potencier <fabien.potencier@symfony-project.com>
 */
interface DebugLoggerInterface
{
    /**
     * Returns an array of logs.
     *
     * A log is an array with the following mandatory keys:
     * timestamp, message, priority, and priorityName.
     *
     * @return array An array of logs
     */
    function getLogs();

    /**
     * Returns the number of errors.
     *
     * @return integer The number of errors
     */
    function countErrors();
}
