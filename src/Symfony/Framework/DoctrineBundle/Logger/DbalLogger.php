<?php

namespace Symfony\Framework\DoctrineBundle\Logger;

use Symfony\Components\HttpKernel\LoggerInterface;
use Doctrine\DBAL\Logging\DebugStack;

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

/**
 * DbalLogger.
 *
 * @package    Symfony
 * @subpackage Framework_DoctrineBundle
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class DbalLogger extends DebugStack
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

    public function logSql($sql, array $params = null)
    {
        parent::logSql($sql, $params);

        if (null !== $this->logger) {
            $this->log($sql.' ('.str_replace("\n", '', var_export($params, true)).')');
        }
    }

    /**
     * Logs a message.
     *
     * @param string $message A message to log
     */
    public function log($message)
    {
        $this->logger->info($message);
    }
}
