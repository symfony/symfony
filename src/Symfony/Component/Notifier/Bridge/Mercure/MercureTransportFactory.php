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

use Symfony\Bundle\MercureBundle\MercureBundle;
use Symfony\Component\Mercure\HubRegistry;
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
    private $registry;

    /**
     * @param HubRegistry $registry
     */
    public function __construct(HubRegistry $registry)
    {
        parent::__construct();

        $this->registry = $registry;
    }

    /**
     * @return MercureTransport
     */
    public function create(Dsn $dsn): TransportInterface
    {
        if ('mercure' !== $dsn->getScheme()) {
            throw new UnsupportedSchemeException($dsn, 'mercure', $this->getSupportedSchemes());
        }

        $hubId = $dsn->getHost();
        $topic = $dsn->getOption('topic');

        if ($this->registry instanceof HubRegistry) {
            $hub = $this->registry->getHub($hubId);

            return new MercureTransport($hub, $hubId, $topic);
        }

        if (!$this->publisherLocator->has($hubId)) {
            if (!class_exists(MercureBundle::class) && !$this->publisherLocator->getProvidedServices()) {
                throw new LogicException('No publishers found. Did you forget to install the MercureBundle? Try running "composer require symfony/mercure-bundle".');
            }

            throw new LogicException(sprintf('"%s" not found. Did you mean one of: %s?', $hubId, implode(', ', array_keys($this->publisherLocator->getProvidedServices()))));
        }

        return new MercureTransport($this->publisherLocator->get($hubId), $hubId, $topic);
    }

    protected function getSupportedSchemes(): array
    {
        return ['mercure'];
    }
}
