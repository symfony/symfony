<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Doctrine\Logger;

use Symfony\Component\HttpKernel\Log\LoggerInterface;
use Symfony\Component\HttpKernel\Debug\Stopwatch;
use Doctrine\DBAL\Logging\SQLLogger;

/**
 * DbalLogger.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class DbalLogger implements SQLLogger
{
    const MAX_STRING_LENGTH = 32;
    const BINARY_DATA_VALUE = '(binary value)';

    protected $logger;
    protected $stopwatch;

    /**
     * Constructor.
     *
     * @param LoggerInterface $logger    A LoggerInterface instance
     * @param Stopwatch       $stopwatch A Stopwatch instance
     */
    public function __construct(LoggerInterface $logger = null, Stopwatch $stopwatch = null)
    {
        $this->logger = $logger;
        $this->stopwatch = $stopwatch;
    }

    /**
     * {@inheritdoc}
     */
    public function startQuery($sql, array $params = null, array $types = null)
    {
        if (null !== $this->stopwatch) {
            $this->stopwatch->start('doctrine', 'doctrine');
        }

        if (is_array($params)) {
            foreach ($params as $index => $param) {
                if (!is_string($params[$index])) {
                    continue;
                }

                // non utf-8 strings break json encoding
                if (!preg_match('#[\p{L}\p{N} ]#u', $params[$index])) {
                    $params[$index] = self::BINARY_DATA_VALUE;
                    continue;
                }

                // detect if the too long string must be shorten
                if (function_exists('mb_detect_encoding') && false !== $encoding = mb_detect_encoding($params[$index])) {
                    if (self::MAX_STRING_LENGTH < mb_strlen($params[$index], $encoding)) {
                        $params[$index] = mb_substr($params[$index], 0, self::MAX_STRING_LENGTH - 6, $encoding).' [...]';
                        continue;
                    }
                } else {
                    if (self::MAX_STRING_LENGTH < strlen($params[$index])) {
                        $params[$index] = substr($params[$index], 0, self::MAX_STRING_LENGTH - 6).' [...]';
                        continue;
                    }
                }
            }
        }

        if (null !== $this->logger) {
            $this->log($sql, null === $params ? array() : $params);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function stopQuery()
    {
        if (null !== $this->stopwatch) {
            $this->stopwatch->stop('doctrine');
        }
    }

    /**
     * Logs a message.
     *
     * @param string $message A message to log
     * @param array  $params  The context
     */
    protected function log($message, array $params)
    {
        $this->logger->debug($message, $params);
    }
}
