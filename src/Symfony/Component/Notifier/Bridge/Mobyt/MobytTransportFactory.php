<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Mobyt;

use Symfony\Component\Notifier\Exception\UnsupportedSchemeException;
use Symfony\Component\Notifier\Transport\AbstractTransportFactory;
use Symfony\Component\Notifier\Transport\Dsn;
use Symfony\Component\Notifier\Transport\TransportInterface;

final class MobytTransportFactory extends AbstractTransportFactory
{
    /**
     * @return MobytTransport
     */
    public function create(Dsn $dsn): TransportInterface
    {
        $scheme = $dsn->getScheme();

        if ('mobyt' !== $scheme) {
            throw new UnsupportedSchemeException($dsn, 'mobyt', $this->getSupportedSchemes());
        }

        $accountSid = $this->getUser($dsn);
        $authToken = $this->getPassword($dsn);
        $from = $dsn->getRequiredOption('from');
        $typeQuality = $dsn->getOption('type_quality');
        $host = 'default' === $dsn->getHost() ? null : $dsn->getHost();
        $port = $dsn->getPort();

        return (new MobytTransport($accountSid, $authToken, $from, $typeQuality, $this->client, $this->dispatcher))->setHost($host)->setPort($port);
    }

    protected function getSupportedSchemes(): array
    {
        return ['mobyt'];
    }
}
