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

use Doctrine\DBAL\Logging\SQLLogger;
use Psr\Log\LoggerInterface;
use Symfony\Component\Stopwatch\Stopwatch;

trigger_deprecation('symfony/doctrine-bridge', '6.4', '"%s" is deprecated, use a middleware instead.', DbalLogger::class);

/**
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @deprecated since Symfony 6.4, use a middleware instead.
 */
class DbalLogger implements SQLLogger
{
    public const MAX_STRING_LENGTH = 32;
    public const BINARY_DATA_VALUE = '(binary value)';

    protected $logger;
    protected $stopwatch;

    public function __construct(?LoggerInterface $logger = null, ?Stopwatch $stopwatch = null)
    {
        $this->logger = $logger;
        $this->stopwatch = $stopwatch;
    }

    public function startQuery($sql, ?array $params = null, ?array $types = null): void
    {
        $this->stopwatch?->start('doctrine', 'doctrine');

        if (null !== $this->logger) {
            $this->log($sql, null === $params ? [] : $this->normalizeParams($params));
        }
    }

    public function stopQuery(): void
    {
        $this->stopwatch?->stop('doctrine');
    }

    /**
     * Logs a message.
     *
     * @return void
     */
    protected function log(string $message, array $params)
    {
        $this->logger->debug($message, $params);
    }

    private function normalizeParams(array $params): array
    {
        foreach ($params as $index => $param) {
            // normalize recursively
            if (\is_array($param)) {
                $params[$index] = $this->normalizeParams($param);
                continue;
            }

            if (!\is_string($params[$index])) {
                continue;
            }

            // non utf-8 strings break json encoding
            if (!preg_match('//u', $params[$index])) {
                $params[$index] = self::BINARY_DATA_VALUE;
                continue;
            }

            // detect if the too long string must be shorten
            if (self::MAX_STRING_LENGTH < mb_strlen($params[$index], 'UTF-8')) {
                $params[$index] = mb_substr($params[$index], 0, self::MAX_STRING_LENGTH - 6, 'UTF-8').' [...]';
                continue;
            }
        }

        return $params;
    }
}
