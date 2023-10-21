<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\ContactEveryone;

use Symfony\Component\Notifier\Exception\UnsupportedSchemeException;
use Symfony\Component\Notifier\Transport\AbstractTransportFactory;
use Symfony\Component\Notifier\Transport\Dsn;

/**
 * @author Franck Ranaivo-Harisoa <franckranaivo@gmail.com>
 */
final class ContactEveryoneTransportFactory extends AbstractTransportFactory
{
    public function create(Dsn $dsn): ContactEveryoneTransport
    {
        if ('contact-everyone' !== $dsn->getScheme()) {
            throw new UnsupportedSchemeException($dsn, 'contact-everyone', $this->getSupportedSchemes());
        }

        $token = $this->getUser($dsn);
        $host = 'default' === $dsn->getHost() ? null : $dsn->getHost();
        $diffusionName = $dsn->getOption('diffusionname');
        $category = $dsn->getOption('category');

        return (new ContactEveryoneTransport($token, $diffusionName, $category, $this->client, $this->dispatcher))->setHost($host)->setPort($dsn->getPort());
    }

    protected function getSupportedSchemes(): array
    {
        return ['contact-everyone'];
    }
}
