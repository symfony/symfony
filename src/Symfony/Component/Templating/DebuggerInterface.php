<?php

namespace Symfony\Component\Templating;

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * DebuggerInterface is the interface you need to implement
 * to debug template loader instances.
 *
 * @author Fabien Potencier <fabien.potencier@symfony-project.com>
 */
interface DebuggerInterface
{
    /**
     * Logs a message.
     *
     * @param string $message A message to log
     */
    function log($message);
}
