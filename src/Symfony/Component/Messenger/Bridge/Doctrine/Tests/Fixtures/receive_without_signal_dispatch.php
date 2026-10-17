<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Doctrine\DBAL\Driver\PDO\Exception;
use Doctrine\DBAL\Exception\DeadlockException;
use Symfony\Component\Messenger\Bridge\Doctrine\Transport\Connection;
use Symfony\Component\Messenger\Bridge\Doctrine\Transport\DoctrineReceiver;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\TransportException;
use Symfony\Component\Messenger\Transport\Serialization\PhpSerializer;

$vendor = __DIR__;
while (!is_file($vendor.'/vendor/autoload.php')) {
    if ($vendor === \dirname($vendor)) {
        throw new \RuntimeException('Run "composer install" before running this fixture.');
    }
    $vendor = \dirname($vendor);
}
require $vendor.'/vendor/autoload.php';

if (\function_exists('pcntl_signal_dispatch')) {
    throw new \LogicException('pcntl_signal_dispatch must be disabled.');
}
echo "Signal dispatch disabled\n";

$result = $argv[1];
$asyncSignals = (bool) $argv[2];
pcntl_async_signals($asyncSignals);

$connection = new class($result, $asyncSignals) extends Connection {
    public function __construct(private string $result, private bool $asyncSignals)
    {
    }

    public function get(): ?array
    {
        if ($this->asyncSignals !== pcntl_async_signals()) {
            throw new \LogicException('Signals must not be suspended when dispatch is unavailable.');
        }

        return match ($this->result) {
            'empty' => null,
            'message' => ['id' => 1, 'headers' => []] + (new PhpSerializer())->encode(new Envelope(new \stdClass())),
            'retryable' => throw new DeadlockException(Exception::new(new \PDOException('Deadlock', 40001)), null),
            'exception' => throw new \RuntimeException('Fetch failed.'),
        };
    }
};
$receiver = new DoctrineReceiver($connection);

try {
    $envelopes = $receiver->get();
    echo $envelopes ? "message\n" : ($result === 'retryable' ? "retryable\n" : "empty\n");

    if ('retryable' === $result) {
        $receiver->get();
        try {
            $receiver->get();
            throw new \LogicException('TransportException expected.');
        } catch (TransportException) {
        }
    }
} catch (\RuntimeException $e) {
    if ('exception' !== $result || 'Fetch failed.' !== $e->getMessage()) {
        throw $e;
    }
    echo "exception\n";
}

if ($asyncSignals !== pcntl_async_signals()) {
    throw new \LogicException('The asynchronous signal state was changed.');
}
echo "Signal state preserved\n";
