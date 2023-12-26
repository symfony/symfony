<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Smsbox;

use Symfony\Component\Notifier\Bridge\Smsbox\Enum\Mode;
use Symfony\Component\Notifier\Bridge\Smsbox\Enum\Strategy;
use Symfony\Component\Notifier\Exception\UnsupportedSchemeException;
use Symfony\Component\Notifier\Transport\AbstractTransportFactory;
use Symfony\Component\Notifier\Transport\Dsn;

/**
 * @author Alan Zarli <azarli@smsbox.fr>
 * @author Farid Touil <ftouil@smsbox.fr>
 */
final class SmsboxTransportFactory extends AbstractTransportFactory
{
    public function create(Dsn $dsn): SmsboxTransport
    {
        $scheme = $dsn->getScheme();

        if ('smsbox' !== $scheme) {
            throw new UnsupportedSchemeException($dsn, 'smsbox', $this->getSupportedSchemes());
        }

        $apiKey = $this->getUser($dsn);
        $mode = Mode::from($dsn->getRequiredOption('mode'));
        $strategy = Strategy::from($dsn->getRequiredOption('strategy'));
        $sender = $dsn->getOption('sender');

        if (Mode::Expert === $mode) {
            $sender = $dsn->getRequiredOption('sender');
        }

        $host = 'default' === $dsn->getHost() ? null : $dsn->getHost();
        $port = $dsn->getPort();

        return (new SmsboxTransport($apiKey, $mode, $strategy, $sender, $this->client, $this->dispatcher))
            ->setHost($host)
            ->setPort($port);
    }

    protected function getSupportedSchemes(): array
    {
        return ['smsbox'];
    }
}
