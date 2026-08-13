<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Doctrine\Messenger;

use Doctrine\Persistence\ConnectionRegistry;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\StackInterface;
use Symfony\Component\Messenger\Stamp\ConsumedByWorkerStamp;

/**
 * Closes connections and therefore saves number of connections.
 *
 * @author Fuong <insidestyles@gmail.com>
 */
class DoctrineDbalCloseConnectionMiddleware extends AbstractDoctrineDbalMiddleware
{
    /** @var list<string> */
    private array $connectionNames;

    /**
     * @param list<string>|string $connectionNames or none to close them all
     */
    public function __construct(ConnectionRegistry $connectionRegistry, array|string $connectionNames = [])
    {
        parent::__construct($connectionRegistry);

        $this->connectionNames = (array) $connectionNames;
    }

    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        $connections = $this->getConnections($this->connectionNames);

        try {
            return $stack->next()->handle($envelope, $stack);
        } finally {
            if ($envelope->last(ConsumedByWorkerStamp::class)) {
                foreach ($connections as $connection) {
                    $connection->close();
                }
            }
        }
    }
}
