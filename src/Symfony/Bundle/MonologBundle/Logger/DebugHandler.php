<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\MonologBundle\Logger;

use Monolog\Handler\TestHandler;
use Monolog\Logger;
use Symfony\Component\HttpKernel\Log\DebugLoggerInterface;

/**
 * DebugLogger.
 *
 * @author Jordi Boggiano <j.boggiano@seld.be>
 */
class DebugHandler extends TestHandler implements DebugLoggerInterface
{
    /**
     * {@inheritdoc}
     */
    public function getLogs()
    {
        return $this->messages;
    }

    /**
     * {@inheritdoc}
     */
    public function countErrors()
    {
        return count($this->messagesByLevel[Logger::ERROR]);
    }
}
