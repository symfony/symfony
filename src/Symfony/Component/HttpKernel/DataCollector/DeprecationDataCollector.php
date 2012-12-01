<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\DataCollector;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Log\DebugLoggerInterface;
use Symfony\Bridge\Monolog\Logger;

/**
 * DeprecationDataCollector.
 *
 * @author Colin Frei <colin@colinfrei.com>
 */
class DeprecationDataCollector extends DataCollector
{
    private $logger;

    public function __construct(DebugLoggerInterface $logger = null)
    {
        $this->logger = $logger;
    }

    /**
     * {@inheritdoc}
     */
    public function collect(Request $request, Response $response, \Exception $exception = null)
    {
        if (null === $this->logger) {
            return;
        }

        $count = 0;
        foreach ($this->logger->getLogs() as $log) {
            if (Logger::WARNING === $log['priority']) {
                $count++;
            }
        }

        $this->data = array(
            'count' => $count,
        );
    }

    public function getCount()
    {
        return $this->data['count'];
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'deprecation';
    }
}
