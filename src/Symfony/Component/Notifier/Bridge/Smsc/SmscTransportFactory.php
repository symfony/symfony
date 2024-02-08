<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Smsc;

use Symfony\Component\Notifier\Exception\UnsupportedSchemeException;
use Symfony\Component\Notifier\Transport\AbstractTransportFactory;
use Symfony\Component\Notifier\Transport\Dsn;

/**
 * @author Valentin Nazarov <i.kozlice@protonmail.com>
 */
final class SmscTransportFactory extends AbstractTransportFactory
{
    public function create(Dsn $dsn): SmscTransport
    {
        $scheme = $dsn->getScheme();

        if ('smsc' !== $scheme) {
            throw new UnsupportedSchemeException($dsn, 'smsc', $this->getSupportedSchemes());
        }

        $login = $dsn->getUser();
        $password = $dsn->getPassword();
        $from = $dsn->getRequiredOption('from');
        $host = 'default' === $dsn->getHost() ? null : $dsn->getHost();

        return (new SmscTransport($login, $password, $from, $this->client, $this->dispatcher))->setHost($host);
    }

    protected function getSupportedSchemes(): array
    {
        return ['smsc'];
    }
}
