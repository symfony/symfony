<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Ntfy;

use Symfony\Component\Notifier\Exception\UnsupportedSchemeException;
use Symfony\Component\Notifier\Transport\AbstractTransportFactory;
use Symfony\Component\Notifier\Transport\Dsn;
use Symfony\Component\Notifier\Transport\TransportInterface;

/**
 * @author Mickael Perraud <mikaelkael.fr@gmail.com>
 */
final class NtfyTransportFactory extends AbstractTransportFactory
{
    public function create(Dsn $dsn): TransportInterface
    {
        if ('ntfy' !== $dsn->getScheme()) {
            throw new UnsupportedSchemeException($dsn, 'ntfy', $this->getSupportedSchemes());
        }

        $host = 'default' === $dsn->getHost() ? null : $dsn->getHost();
        $topic = substr($dsn->getPath(), 1);

        if (\in_array($dsn->getOption('secureHttp', true), [0, false, 'false', 'off', 'no'], true)) {
            $secureHttp = false;
        } else {
            $secureHttp = true;
        }

        $transport = (new NtfyTransport($topic, $secureHttp))->setHost($host);
        if ($port = $dsn->getPort()) {
            $transport->setPort($port);
        }

        if ($user = $dsn->getUser()) {
            $transport->setUser($user);
        }

        if ($password = $dsn->getPassword()) {
            $transport->setPassword($password);
        }

        return $transport;
    }

    protected function getSupportedSchemes(): array
    {
        return ['ntfy'];
    }
}
