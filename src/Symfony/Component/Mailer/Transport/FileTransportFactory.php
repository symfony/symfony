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

use Symfony\Component\Mailer\Exception\InvalidArgumentException;
use Symfony\Component\Mailer\Exception\UnsupportedSchemeException;

/**
 * @author ...
 */
final class FileTransportFactory extends AbstractTransportFactory
{
    public function create(Dsn $dsn): TransportInterface
    {
        if ('file' !== $dsn->getScheme()) {
            throw new UnsupportedSchemeException($dsn, 'file', $this->getSupportedSchemes());
        }

        $dir = \dirname($dsn->getPath());
        if (!is_dir($dir) || !is_writable($dir)) {
            throw new InvalidArgumentException("Directory $dir doesn't exist or is not writable.");
        }

        return new FileTransport($dsn, $this->dispatcher, $this->logger);
    }

    protected function getSupportedSchemes(): array
    {
        return ['file'];
    }
}
