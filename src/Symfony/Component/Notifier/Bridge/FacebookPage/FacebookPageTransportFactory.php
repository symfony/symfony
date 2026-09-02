<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\FacebookPage;

use Symfony\Component\Notifier\Exception\UnsupportedSchemeException;
use Symfony\Component\Notifier\Transport\AbstractTransportFactory;
use Symfony\Component\Notifier\Transport\Dsn;

/**
 * @author Mathieu Ledru <matyo91@gmail.com>
 */
final class FacebookPageTransportFactory extends AbstractTransportFactory
{
    private const DEFAULT_API_VERSION = 'v26.0';

    public function create(Dsn $dsn): FacebookPageTransport
    {
        $scheme = $dsn->getScheme();

        if ('facebook-page' !== $scheme) {
            throw new UnsupportedSchemeException($dsn, 'facebook-page', $this->getSupportedSchemes());
        }

        $pageAccessToken = $this->getUser($dsn);
        $pageId = $dsn->getRequiredOption('page_id');
        $apiVersion = $dsn->getOption('api_version', self::DEFAULT_API_VERSION);
        $host = 'default' === $dsn->getHost() ? null : $dsn->getHost();
        $port = $dsn->getPort();

        return (new FacebookPageTransport($pageAccessToken, $pageId, $apiVersion, $this->client, $this->dispatcher))->setHost($host)->setPort($port)->setSsl($this->getSsl($dsn));
    }

    protected function getSupportedSchemes(): array
    {
        return ['facebook-page'];
    }
}
