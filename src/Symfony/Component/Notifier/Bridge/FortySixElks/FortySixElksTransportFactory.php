<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\FortySixElks;

use Symfony\Component\Notifier\Exception\UnsupportedSchemeException;
use Symfony\Component\Notifier\Transport\AbstractTransportFactory;
use Symfony\Component\Notifier\Transport\Dsn;

/**
 * @author Jon Gotlin <jon@jon.se>
 */
final class FortySixElksTransportFactory extends AbstractTransportFactory
{
    public function create(Dsn $dsn): FortySixElksTransport
    {
        if ('forty-six-elks' !== $dsn->getScheme()) {
            throw new UnsupportedSchemeException($dsn, 'forty-six-elks', $this->getSupportedSchemes());
        }

        $host = 'default' === $dsn->getHost() ? null : $dsn->getHost();
        $from = $dsn->getRequiredOption('from');

        return (new FortySixElksTransport($this->getUser($dsn), $this->getPassword($dsn), $from, $this->client, $this->dispatcher))->setHost($host)->setPort($dsn->getPort());
    }

    protected function getSupportedSchemes(): array
    {
        return ['forty-six-elks'];
    }
}
