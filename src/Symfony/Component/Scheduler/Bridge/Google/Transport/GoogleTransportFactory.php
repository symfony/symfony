<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Scheduler\Bridge\Google\Transport;

use Symfony\Component\Scheduler\Bridge\Google\Task\JobFactory;
use Symfony\Component\Scheduler\Transport\Dsn;
use Symfony\Component\Scheduler\Transport\TransportFactoryInterface;
use Symfony\Component\Scheduler\Transport\TransportInterface;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class GoogleTransportFactory implements TransportFactoryInterface
{
    private $jobFactory;

    public function __construct(JobFactory $jobFactory)
    {
        $this->jobFactory = $jobFactory;
    }

    /**
     * {@inheritdoc}
     */
    public function createTransport(Dsn $dsn, array $options, SerializerInterface $serializer): TransportInterface
    {
        return new GoogleTransport($dsn, $options, $this->jobFactory);
    }

    /**
     * {@inheritdoc}
     */
    public function support(string $dsn, array $options = []): bool
    {
        return 0 === strpos($dsn, 'google://') || 0 === strpos('gcp://', $dsn);
    }
}
