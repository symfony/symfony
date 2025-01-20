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
 *
 * @implements TransportFactoryInterface<TransportInterface>
 */
class TransportFactory implements TransportFactoryInterface
{
    /**
     * @param iterable<mixed, TransportFactoryInterface> $factories
     */
    public function __construct(
        private iterable $factories,
    ) {
    }

    public function createTransport(#[\SensitiveParameter] string $dsn, array $options, SerializerInterface $serializer): TransportInterface
    {
        foreach ($this->factories as $factory) {
            if ($factory->supports($dsn, $options)) {
                return $factory->createTransport($dsn, $options, $serializer);
            }
        }

        // Help the user to select Symfony packages based on protocol.
        $packageSuggestion = '';
        if (str_starts_with($dsn, 'amqp://')) {
            $packageSuggestion = ' Run "composer require symfony/amqp-messenger" to install AMQP transport.';
        } elseif (str_starts_with($dsn, 'doctrine://')) {
            $packageSuggestion = ' Run "composer require symfony/doctrine-messenger" to install Doctrine transport.';
        } elseif (str_starts_with($dsn, 'redis://') || str_starts_with($dsn, 'rediss://')) {
            $packageSuggestion = ' Run "composer require symfony/redis-messenger" to install Redis transport.';
        } elseif (str_starts_with($dsn, 'sqs://') || preg_match('#^https://sqs\.[\w\-]+\.amazonaws\.com/.+#', $dsn)) {
            $packageSuggestion = ' Run "composer require symfony/amazon-sqs-messenger" to install Amazon SQS transport.';
        } elseif (str_starts_with($dsn, 'beanstalkd://')) {
            $packageSuggestion = ' Run "composer require symfony/beanstalkd-messenger" to install Beanstalkd transport.';
        }

        throw new InvalidArgumentException('No transport supports the given Messenger DSN.'.$packageSuggestion);
    }

    public function supports(#[\SensitiveParameter] string $dsn, array $options): bool
    {
        foreach ($this->factories as $factory) {
            if ($factory->supports($dsn, $options)) {
                return true;
            }
        }

        return false;
    }
}
