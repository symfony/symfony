<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Mercure;

use Symfony\Component\Mercure\PublisherInterface;
use Symfony\Component\Notifier\Exception\LogicException;
use Symfony\Component\Notifier\Exception\UnsupportedSchemeException;
use Symfony\Component\Notifier\Transport\AbstractTransportFactory;
use Symfony\Component\Notifier\Transport\Dsn;
use Symfony\Component\Notifier\Transport\TransportInterface;
use Symfony\Contracts\Service\ServiceProviderInterface;

/**
 * @author Mathias Arlaud <mathias.arlaud@gmail.com>
 */
final class MercureTransportFactory extends AbstractTransportFactory
{
    private $publisherLocator;

    /**
     * @param ServiceProviderInterface $publisherLocator A container that holds {@see PublisherInterface} instances
     */
    public function __construct(ServiceProviderInterface $publisherLocator)
    {
        parent::__construct();

        $this->publisherLocator = $publisherLocator;
    }

    /**
     * @return MercureTransport
     */
    public function create(Dsn $dsn): TransportInterface
    {
        if ('mercure' !== $dsn->getScheme()) {
            throw new UnsupportedSchemeException($dsn, 'mercure', $this->getSupportedSchemes());
        }

        $publisherId = $dsn->getHost();
        if (!$this->publisherLocator->has($publisherId)) {
            throw new LogicException(sprintf('"%s" not found. Did you mean one of: %s?', $publisherId, implode(', ', array_keys($this->publisherLocator->getProvidedServices()))));
        }

        $topic = $dsn->getOption('topic');

        return new MercureTransport($this->publisherLocator->get($publisherId), $publisherId, $topic);
    }

    protected function getSupportedSchemes(): array
    {
        return ['mercure'];
    }
}
