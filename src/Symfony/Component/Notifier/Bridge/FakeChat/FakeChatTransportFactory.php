<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\FakeChat;

use Symfony\Component\Notifier\Exception\UnsupportedSchemeException;
use Symfony\Component\Notifier\Transport\AbstractTransportFactory;
use Symfony\Component\Notifier\Transport\Dsn;
use Symfony\Component\Notifier\Transport\TransportInterface;
use Symfony\Contracts\Service\ServiceProviderInterface;

/**
 * @author Oskar Stark <oskarstark@googlemail.com>
 */
final class FakeChatTransportFactory extends AbstractTransportFactory
{
    protected $serviceProvider;

    public function __construct(ServiceProviderInterface $serviceProvider)
    {
        parent::__construct();

        $this->serviceProvider = $serviceProvider;
    }

    /**
     * @return FakeChatEmailTransport
     */
    public function create(Dsn $dsn): TransportInterface
    {
        $scheme = $dsn->getScheme();

        if (!\in_array($scheme, $this->getSupportedSchemes())) {
            throw new UnsupportedSchemeException($dsn, 'fakechat', $this->getSupportedSchemes());
        }

        if ('fakechat+email' === $scheme) {
            $serviceId = $dsn->getHost();
            $to = $dsn->getRequiredOption('to');
            $from = $dsn->getRequiredOption('from');

            return (new FakeChatEmailTransport($this->serviceProvider->get($serviceId), $to, $from))->setHost($serviceId);
        }
    }

    protected function getSupportedSchemes(): array
    {
        return ['fakechat+email'];
    }
}
