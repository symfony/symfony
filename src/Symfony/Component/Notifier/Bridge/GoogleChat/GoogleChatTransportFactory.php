<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\GoogleChat;

use Symfony\Component\Notifier\Exception\UnsupportedSchemeException;
use Symfony\Component\Notifier\Transport\AbstractTransportFactory;
use Symfony\Component\Notifier\Transport\Dsn;

/**
 * @author Jérôme Tamarelle <jerome@tamarelle.net>
 */
final class GoogleChatTransportFactory extends AbstractTransportFactory
{
    /**
     * @param Dsn $dsn Format: googlechat://<key>:<token>@default/<space>?thread_key=<thread>
     */
    public function create(Dsn $dsn): GoogleChatTransport
    {
        $scheme = $dsn->getScheme();

        if ('googlechat' !== $scheme) {
            throw new UnsupportedSchemeException($dsn, 'googlechat', $this->getSupportedSchemes());
        }

        $space = explode('/', $dsn->getPath())[1];
        $accessKey = $this->getUser($dsn);
        $accessToken = $this->getPassword($dsn);
        $threadKey = $dsn->getOption('thread_key');
        $host = 'default' === $dsn->getHost() ? null : $dsn->getHost();
        $port = $dsn->getPort();

        return (new GoogleChatTransport($space, $accessKey, $accessToken, $threadKey, $this->client, $this->dispatcher))->setHost($host)->setPort($port);
    }

    protected function getSupportedSchemes(): array
    {
        return ['googlechat'];
    }
}
