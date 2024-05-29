<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Unifonic;

use Symfony\Component\Notifier\Exception\UnsupportedSchemeException;
use Symfony\Component\Notifier\Transport\AbstractTransportFactory;
use Symfony\Component\Notifier\Transport\Dsn;

/**
 * @author Farhad Safarov <farhad.safarov@gmail.com>
 */
final class UnifonicTransportFactory extends AbstractTransportFactory
{
    public function create(Dsn $dsn): UnifonicTransport
    {
        if ('unifonic' !== $dsn->getScheme()) {
            throw new UnsupportedSchemeException($dsn, 'unifonic', $this->getSupportedSchemes());
        }

        $host = 'default' === $dsn->getHost() ? null : $dsn->getHost();

        return (new UnifonicTransport(
            $this->getUser($dsn),
            $dsn->getOption('from'),
            $this->client,
            $this->dispatcher,
        ))->setHost($host)->setPort($dsn->getPort());
    }

    protected function getSupportedSchemes(): array
    {
        return ['unifonic'];
    }
}
