<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Firebase;

use Symfony\Component\Notifier\Exception\MissingRequiredOptionException;
use Symfony\Component\Notifier\Exception\UnsupportedSchemeException;
use Symfony\Component\Notifier\Transport\AbstractTransportFactory;
use Symfony\Component\Notifier\Transport\Dsn;

/**
 * @author Jeroen Spee <https://github.com/Jeroeny>
 * @author Vojtech Smejkal <https://vojtechsmejkal.cz>
 */
final class FirebaseTransportFactory extends AbstractTransportFactory
{
    public function create(Dsn $dsn): FirebaseTransport
    {
        $scheme = $dsn->getScheme();

        if ('firebase' !== $scheme) {
            throw new UnsupportedSchemeException($dsn, 'firebase', $this->getSupportedSchemes());
        }

        $host = 'default' === $dsn->getHost() ? null : $dsn->getHost();
        $port = $dsn->getPort();
        $user = $this->getUser($dsn);

        try {
            $projectId = $dsn->getRequiredOption('project_id');
            $privateKeyId = $dsn->getRequiredOption('private_key_id');
            $privateKey = $dsn->getRequiredOption('private_key');

            return (new FirebaseTransport('', $projectId, $user, $privateKeyId, $privateKey, $this->client, $this->dispatcher))
                ->setHost($host)
                ->setPort($port)
                ->setSsl($this->getSsl($dsn));
        } catch (MissingRequiredOptionException) {
            trigger_deprecation('symfony/firebase-notifier', '8.2', 'Using Firebase Notifier without project_id, private_key_id and private_key options is deprecated. Update your Firebase DSN.');

            return (new FirebaseTransport('', '', '', '', '', $this->client, $this->dispatcher))
                ->setHost($host)
                ->setPort($port)
                ->setSsl($this->getSsl($dsn));
        }
    }

    protected function getSupportedSchemes(): array
    {
        return ['firebase'];
    }
}
