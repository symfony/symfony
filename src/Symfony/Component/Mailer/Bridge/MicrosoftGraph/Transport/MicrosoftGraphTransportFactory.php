<?php

declare(strict_types=1);

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Bridge\MicrosoftGraph\Transport;

use Microsoft\Graph\Core\NationalCloud;
use Microsoft\Kiota\Authentication\Oauth\ClientCredentialContext;
use Symfony\Component\Mailer\Exception\IncompleteDsnException;
use Symfony\Component\Mailer\Exception\InvalidArgumentException;
use Symfony\Component\Mailer\Exception\UnsupportedSchemeException;
use Symfony\Component\Mailer\Transport\AbstractTransportFactory;
use Symfony\Component\Mailer\Transport\Dsn;
use Symfony\Component\Mailer\Transport\TransportInterface;

final class MicrosoftGraphTransportFactory extends AbstractTransportFactory
{
    private const CLOUD_MAP = [
        'default' => NationalCloud::GLOBAL,
        'germany' => NationalCloud::GERMANY,
        'china' => NationalCloud::CHINA,
        'us-dod' => NationalCloud::US_DOD,
        'us-gov' => NationalCloud::US_GOV,
    ];

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
        if (null === $tenantId) {
            throw new IncompleteDsnException("Transport 'microsoft+graph' requires the 'tenant' option.");
        }
        if (!isset(self::CLOUD_MAP[$dsn->getHost()])) {
            throw new InvalidArgumentException(\sprintf("Transport 'microsoft+graph' one of these hosts : '%s'", implode(', ', self::CLOUD_MAP)));
        }

        return new MicrosoftGraphTransport(
            self::CLOUD_MAP[$dsn->getHost()],
            new ClientCredentialContext(
                $tenantId,
                $this->getUser($dsn),
                $this->getPassword($dsn)
            )
        );
    }
}
