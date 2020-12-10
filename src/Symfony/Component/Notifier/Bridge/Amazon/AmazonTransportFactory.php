<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Amazon;

use AsyncAws\Core\Configuration;
use AsyncAws\Sns\SnsClient;
use Symfony\Component\Notifier\Exception\UnsupportedSchemeException;
use Symfony\Component\Notifier\Transport\AbstractTransportFactory;
use Symfony\Component\Notifier\Transport\Dsn;
use Symfony\Component\Notifier\Transport\TransportInterface;

/**
 * @author Adrien Chinour <github@chinour.fr>
 *
 * @experimental in 5.3
 */
final class AmazonTransportFactory extends AbstractTransportFactory
{
    private const DSN_SCHEME = 'sns';

    public function create(Dsn $dsn): TransportInterface
    {
        $scheme = $dsn->getScheme();

        if (self::DSN_SCHEME === $scheme) {
            $options = [
                Configuration::OPTION_PROFILE => $dsn->getOption('profile', 'default'),
                Configuration::OPTION_REGION => $dsn->getOption('region', Configuration::DEFAULT_REGION),
            ];

            if (null !== $dsn->getUser() && null !== $dsn->getPassword()) {
                $options[Configuration::OPTION_ACCESS_KEY_ID] = $dsn->getUser();
                $options[Configuration::OPTION_SECRET_ACCESS_KEY] = $dsn->getPassword();
            }

            return new AmazonTransport(new SnsClient($options, null, $this->client), $this->client, $this->dispatcher);
        }

        throw new UnsupportedSchemeException($dsn, self::DSN_SCHEME, $this->getSupportedSchemes());
    }

    protected function getSupportedSchemes(): array
    {
        return [self::DSN_SCHEME];
    }
}
