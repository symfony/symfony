<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Bridge\Sendinblue\Transport;

use Symfony\Component\Mailer\Exception\UnsupportedSchemeException;
use Symfony\Component\Mailer\Transport\AbstractTransportFactory;
use Symfony\Component\Mailer\Transport\Dsn;
use Symfony\Component\Mailer\Transport\TransportInterface;

/**
 * @author Yann LUCAS
 */
final class SendinblueTransportFactory extends AbstractTransportFactory
{
    public function create(Dsn $dsn): TransportInterface
    {
        if (!\in_array($dsn->getScheme(), $this->getSupportedSchemes(), true)) {
            throw new UnsupportedSchemeException($dsn, 'sendinblue', $this->getSupportedSchemes());
        }

        switch ($dsn->getScheme()) {
            default:
            case 'sendinblue':
            case 'sendinblue+smtp':
                $transport = SendinblueSmtpTransport::class;
                break;
            case 'sendinblue+api':
                return (new SendinblueApiTransport($this->getUser($dsn), $this->client, $this->dispatcher, $this->logger))
                    ->setHost('default' === $dsn->getHost() ? null : $dsn->getHost())
                    ->setPort($dsn->getPort())
                ;
        }

        return new $transport($this->getUser($dsn), $this->getPassword($dsn), $this->dispatcher, $this->logger);
    }

    protected function getSupportedSchemes(): array
    {
        return ['sendinblue', 'sendinblue+smtp', 'sendinblue+api'];
    }
}
