<?php

declare(strict_types=1);
/*
 * This file is part of the Symfony package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Bridge\MicrosoftGraph\Transport;

use Symfony\Component\Mailer\Exception\IncompleteDsnException;
use Symfony\Component\Mailer\Exception\UnsupportedSchemeException;
use Symfony\Component\Mailer\Transport\AbstractTransportFactory;
use Symfony\Component\Mailer\Transport\Dsn;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Contracts\Cache\CacheInterface;

final class MicrosoftGraphTransportFactory extends AbstractTransportFactory
{
    public function __construct(
        private readonly CacheInterface $cache,
    ) {
        parent::__construct();
    }

    /**
     * @return string[]
     */
    protected function getSupportedSchemes(): array
    {
        return ['microsoft+graph'];
    }

    public function create(Dsn $dsn): TransportInterface
    {
        if ('microsoft+graph' !== $dsn->getScheme()) {
            throw new UnsupportedSchemeException($dsn, 'microsoft graph', $this->getSupportedSchemes());
        }
        $tenantId = $dsn->getOption('tenant');
        if ($tenantId === null){
            throw new IncompleteDsnException("Transport 'microsoft+graph' requires the 'tenant' option");
        }

        $graphEndpoint = $dsn->getOption('graphEndpoint', 'https://graph.microsoft.com');
        $authHost = 'default' === $dsn->getHost() ? 'https://login.microsoftonline.com' : $dsn->getHost();
        // This parses the MAILER_DSN containing Microsoft Graph API credentials
        return new MicrosoftGraphTransport(
            $this->getUser($dsn),
            $this->getPassword($dsn),
            $authHost . '/' . $tenantId . '/oauth2/v2.0/token',
            $graphEndpoint,
            $this->cache
        );
    }
}
