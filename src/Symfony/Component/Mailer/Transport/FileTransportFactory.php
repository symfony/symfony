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
 * @author Hugo Alliaume <@kocal>
 */
final class FileTransportFactory extends AbstractTransportFactory
{
    public function create(Dsn $dsn): TransportInterface
    {
        if ('file' === $dsn->getScheme()) {
            return new FileTransport($dsn->getOption('path'), $this->dispatcher, $this->logger);
        }

        throw new UnsupportedSchemeException($dsn);
    }

    public function supports(Dsn $dsn): bool
    {
        return 'null' === $dsn->getHost() && null !== $dsn->getOption('path');
    }
}
