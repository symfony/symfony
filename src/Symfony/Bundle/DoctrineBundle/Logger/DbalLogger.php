<?php

namespace Symfony\Bundle\DoctrineBundle\Logger;

use Symfony\Component\HttpKernel\Log\LoggerInterface;
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

    /**
     * {@inheritdoc}
     */
    public function startQuery($sql, array $params = null, array $types = null)
    {
        parent::startQuery($sql, $params, $types);

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
