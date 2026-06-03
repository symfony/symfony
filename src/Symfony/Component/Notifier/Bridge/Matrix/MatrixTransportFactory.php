<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Matrix;

use Symfony\Component\Notifier\Exception\UnsupportedSchemeException;
use Symfony\Component\Notifier\Transport\AbstractTransportFactory;
use Symfony\Component\Notifier\Transport\Dsn;

/**
 * @author Frank Schulze <frank@akiber.de>
 */
class MatrixTransportFactory extends AbstractTransportFactory
{
    public function create(Dsn $dsn): MatrixTransport
    {
        if ('matrix' !== $dsn->getScheme()) {
            throw new UnsupportedSchemeException($dsn, 'matrix', $this->getSupportedSchemes());
        }

        $token = $dsn->getRequiredOption('accessToken');
        $host = $dsn->getHost();
        $port = $dsn->getPort();
        $ssl = $dsn->getBooleanOption('ssl', true);

        return (new MatrixTransport($token, $ssl, $this->client, $this->dispatcher))->setHost($host)->setPort($port);
    }

    protected function getSupportedSchemes(): array
    {
        return ['matrix'];
    }
}
