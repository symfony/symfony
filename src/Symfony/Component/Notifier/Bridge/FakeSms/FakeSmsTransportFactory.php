<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\FakeSms;

use Symfony\Component\Notifier\Exception\UnsupportedSchemeException;
use Symfony\Component\Notifier\Transport\AbstractTransportFactory;
use Symfony\Component\Notifier\Transport\Dsn;
use Symfony\Component\Notifier\Transport\TransportInterface;
use Symfony\Contracts\Service\ServiceProviderInterface;

/**
 * @author James Hemery <james@yieldstudio.fr>
 */
final class FakeSmsTransportFactory extends AbstractTransportFactory
{
    protected $serviceProvider;

    public function __construct(ServiceProviderInterface $serviceProvider)
    {
        parent::__construct();

        $this->serviceProvider = $serviceProvider;
    }

    /**
     * @return FakeSmsEmailTransport
     */
    public function create(Dsn $dsn): TransportInterface
    {
        $scheme = $dsn->getScheme();

        if (!\in_array($scheme, $this->getSupportedSchemes())) {
            throw new UnsupportedSchemeException($dsn, 'fakesms', $this->getSupportedSchemes());
        }

        if ('fakesms+email' === $scheme) {
            $serviceId = $dsn->getHost();
            $to = $dsn->getRequiredOption('to');
            $from = $dsn->getRequiredOption('from');

            return (new FakeSmsEmailTransport($this->serviceProvider->get($serviceId), $to, $from))->setHost($serviceId);
        }
    }

    protected function getSupportedSchemes(): array
    {
        return ['fakesms+email'];
    }
}
