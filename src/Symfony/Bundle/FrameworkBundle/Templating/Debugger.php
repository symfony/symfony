<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Templating;

use Symfony\Component\Templating\DebuggerInterface;
use Symfony\Component\HttpKernel\Log\LoggerInterface;

/**
 * Binds the Symfony templating loader debugger to the Symfony logger.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class Debugger implements DebuggerInterface
{
    protected $logger;

    /**
     * Constructor.
     *
     * @param LoggerInterface $logger A LoggerInterface instance
     */
    public function __construct(LoggerInterface $logger = null)
    {
        $this->logger = $logger;
    }

    /**
     * Logs a message.
     *
     * @param string $message A message to log
     */
    public function log($message)
    {
        if (null !== $this->logger) {
            $this->logger->info($message);
        }
    }
}
