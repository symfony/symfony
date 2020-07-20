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
use Symfony\Component\HttpClient\HttpClient;
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
            return new SesSmtpTransport($this->getUser($dsn), $this->getPassword($dsn), $region, $this->dispatcher, $this->logger);
        }

        if (!class_exists(SesClient::class)) {
            if (!class_exists(HttpClient::class)) {
                throw new \LogicException(sprintf('You cannot use "%s" as the HttpClient component or AsyncAws package is not installed. Try running "composer require async-aws/ses".', __CLASS__));
            }

            trigger_deprecation('symfony/amazon-mailer', '5.1', 'Using the "%s" transport without AsyncAws is deprecated. Try running "composer require async-aws/ses".', $scheme, \get_called_class());

            $user = $this->getUser($dsn);
            $password = $this->getPassword($dsn);
            $host = 'default' === $dsn->getHost() ? null : $dsn->getHost();
            $port = $dsn->getPort();

            if ('ses+api' === $scheme) {
                return (new SesApiTransport($user, $password, $region, $this->client, $this->dispatcher, $this->logger))->setHost($host)->setPort($port);
            }
            if ('ses+https' === $scheme || 'ses' === $scheme) {
                return (new SesHttpTransport($user, $password, $region, $this->client, $this->dispatcher, $this->logger))->setHost($host)->setPort($port);
            }
        } else {
            switch ($scheme) {
                case 'ses+api':
                    $class = SesApiAsyncAwsTransport::class;
                    // no break
                case 'ses':
                case 'ses+https':
                    $class = $class ?? SesHttpAsyncAwsTransport::class;
                    $options = [
                        'region' => $dsn->getOption('region') ?: 'eu-west-1',
                        'accessKeyId' => $dsn->getUser(),
                        'accessKeySecret' => $dsn->getPassword(),
                    ] + (
                        'default' === $dsn->getHost() ? [] : ['endpoint' => 'https://'.$dsn->getHost().($dsn->getPort() ? ':'.$dsn->getPort() : '')]
                    );

                    return new $class(new SesClient(Configuration::create($options), null, $this->client, $this->logger), $this->dispatcher, $this->logger);
            }
        }

        throw new UnsupportedSchemeException($dsn, 'ses', $this->getSupportedSchemes());
    }

    protected function getSupportedSchemes(): array
    {
        return ['ses', 'ses+api', 'ses+https', 'ses+smtp', 'ses+smtps'];
    }
}
