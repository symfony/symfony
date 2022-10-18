<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Bridge\Amazon\Transport;

use AsyncAws\Core\Configuration;
use AsyncAws\Ses\SesClient;
use Symfony\Component\Mailer\Exception\UnsupportedSchemeException;
use Symfony\Component\Mailer\Transport\AbstractTransportFactory;
use Symfony\Component\Mailer\Transport\Dsn;
use Symfony\Component\Mailer\Transport\TransportInterface;

/**
 * @author Konstantin Myakshin <molodchick@gmail.com>
 * @author Jérémy Derussé <jeremy@derusse.com>
 */
final class SesTransportFactory extends AbstractTransportFactory
{
    public function create(Dsn $dsn): TransportInterface
    {
        $scheme = $dsn->getScheme();
        $region = $dsn->getOption('region');

        if ('ses+smtp' === $scheme || 'ses+smtps' === $scheme) {
            $transport = new SesSmtpTransport($this->getUser($dsn), $this->getPassword($dsn), $region, $this->dispatcher, $this->logger, $dsn->getHost());

            if (null !== $pingThreshold = $dsn->getOption('ping_threshold')) {
                $transport->setPingThreshold((int) $pingThreshold);
            }

            return $transport;
        }

        switch ($scheme) {
            case 'ses+api':
                $class = SesApiAsyncAwsTransport::class;
                // no break
            case 'ses':
            case 'ses+https':
                $class ??= SesHttpAsyncAwsTransport::class;
                $options = [
                    'region' => $dsn->getOption('region') ?: 'eu-west-1',
                    'accessKeyId' => $dsn->getUser(),
                    'accessKeySecret' => $dsn->getPassword(),
                ] + (
                    'default' === $dsn->getHost() ? [] : ['endpoint' => 'https://'.$dsn->getHost().($dsn->getPort() ? ':'.$dsn->getPort() : '')]
                ) + (
                    null === $dsn->getOption('session_token') ? [] : ['sessionToken' => $dsn->getOption('session_token')]
                );

                return new $class(new SesClient(Configuration::create($options), null, $this->client, $this->logger), $this->dispatcher, $this->logger);
        }

        throw new UnsupportedSchemeException($dsn, 'ses', $this->getSupportedSchemes());
    }

    protected function getSupportedSchemes(): array
    {
        return ['ses', 'ses+api', 'ses+https', 'ses+smtp', 'ses+smtps'];
    }
}
