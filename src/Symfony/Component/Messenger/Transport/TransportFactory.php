<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Transport;

use Symfony\Component\Messenger\Exception\InvalidArgumentException;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;

/**
 * @author Samuel Roze <samuel.roze@gmail.com>
 */
class TransportFactory implements TransportFactoryInterface
{
    private $factories;

    /**
     * @param iterable<mixed, TransportFactoryInterface> $factories
     */
    public function __construct(iterable $factories)
    {
        $this->factories = $factories;
    }

    public function createTransport(string $dsn, array $options, SerializerInterface $serializer): TransportInterface
    {
        foreach ($this->factories as $factory) {
            if ($factory->supports($dsn, $options)) {
                return $factory->createTransport($dsn, $options, $serializer);
            }
        }

        // Help the user to select Symfony packages based on protocol.
        $packageSuggestion = '';
        if (0 === strpos($dsn, 'amqp://')) {
            $packageSuggestion = ' Run "composer require symfony/amqp-messenger" to install AMQP transport.';
        } elseif (0 === strpos($dsn, 'doctrine://')) {
            $packageSuggestion = ' Run "composer require symfony/doctrine-messenger" to install Doctrine transport.';
        } elseif (0 === strpos($dsn, 'redis://') || 0 === strpos($dsn, 'rediss://')) {
            $packageSuggestion = ' Run "composer require symfony/redis-messenger" to install Redis transport.';
        } elseif (0 === strpos($dsn, 'sqs://') || preg_match('#^https://sqs\.[\w\-]+\.amazonaws\.com/.+#', $dsn)) {
            $packageSuggestion = ' Run "composer require symfony/amazon-sqs-messenger" to install Amazon SQS transport.';
        } elseif (0 === strpos($dsn, 'beanstalkd://')) {
            $packageSuggestion = ' Run "composer require symfony/beanstalkd-messenger" to install Beanstalkd transport.';
        }

        throw new InvalidArgumentException('No transport supports the given Messenger DSN.'.$packageSuggestion);
    }

    public function supports(string $dsn, array $options): bool
    {
        foreach ($this->factories as $factory) {
            if ($factory->supports($dsn, $options)) {
                return true;
            }
        }

        return false;
    }
}
