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

use Symfony\Component\Mercure\Exception\InvalidArgumentException;
use Symfony\Component\Mercure\HubRegistry;
use Symfony\Component\Notifier\Exception\IncompleteDsnException;
use Symfony\Component\Notifier\Exception\UnsupportedSchemeException;
use Symfony\Component\Notifier\Transport\AbstractTransportFactory;
use Symfony\Component\Notifier\Transport\Dsn;

/**
 * @author Mathias Arlaud <mathias.arlaud@gmail.com>
 */
final class MercureTransportFactory extends AbstractTransportFactory
{
    private HubRegistry $registry;

    public function __construct(HubRegistry $registry)
    {
        parent::__construct();

        $this->registry = $registry;
    }

    public function create(Dsn $dsn): MercureTransport
    {
        if ('mercure' !== $dsn->getScheme()) {
            throw new UnsupportedSchemeException($dsn, 'mercure', $this->getSupportedSchemes());
        }

        $hubId = $dsn->getHost();
        $topic = $dsn->getOption('topic');

        try {
            $hub = $this->registry->getHub($hubId);
        } catch (InvalidArgumentException) {
            throw new IncompleteDsnException(sprintf('Hub "%s" not found. Did you mean one of: "%s"?', $hubId, implode('", "', array_keys($this->registry->all()))));
        }

        return new MercureTransport($hub, $hubId, $topic);
    }

    protected function getSupportedSchemes(): array
    {
        return ['mercure'];
    }
}
