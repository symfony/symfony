<?php

namespace Symfony\Framework\WebBundle\Templating;

use Symfony\Components\Templating\DebuggerInterface;
use Symfony\Foundation\LoggerInterface;

/*
 * This file is part of the symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * Binds the Symfony templating loader debugger to the Symfony logger.
 *
 * @package symfony
 * @author  Fabien Potencier <fabien.potencier@symfony-project.com>
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
    if (null !== $this->logger)
    {
      $this->logger->info($message);
    }
  }
}
