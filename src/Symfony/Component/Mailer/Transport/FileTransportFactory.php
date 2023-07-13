<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Transport;

use Symfony\Component\Mailer\Exception\UnsupportedSchemeException;

/**
 * @author ...
 */
final class FileTransportFactory extends AbstractTransportFactory
{
    public function create(Dsn $dsn): TransportInterface
    {
        if ('file' === $dsn->getScheme()) {
            return new FileTransport($this->dispatcher, $this->logger, $dsn);
        }

        throw new UnsupportedSchemeException($dsn, 'file', $this->getSupportedSchemes());
    }

    protected function getSupportedSchemes(): array
    {
        return ['file'];
    }
}
