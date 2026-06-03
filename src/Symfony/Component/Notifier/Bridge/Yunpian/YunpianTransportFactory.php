<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Yunpian;

use Symfony\Component\Notifier\Exception\UnsupportedSchemeException;
use Symfony\Component\Notifier\Transport\AbstractTransportFactory;
use Symfony\Component\Notifier\Transport\Dsn;

/**
 * @author Mathieu Santostefano <msantostefano@protonmail.com>
 */
class YunpianTransportFactory extends AbstractTransportFactory
{
    public function create(Dsn $dsn): YunpianTransport
    {
        if ('yunpian' !== $dsn->getScheme()) {
            throw new UnsupportedSchemeException($dsn, 'yunpian', $this->getSupportedSchemes());
        }

        $apiKey = $this->getUser($dsn);
        $host = 'default' === $dsn->getHost() ? null : $dsn->getHost();

        return (new YunpianTransport($apiKey, $this->client, $this->dispatcher))->setHost($host)->setPort($dsn->getPort());
    }

    protected function getSupportedSchemes(): array
    {
        return ['yunpian'];
    }
}
