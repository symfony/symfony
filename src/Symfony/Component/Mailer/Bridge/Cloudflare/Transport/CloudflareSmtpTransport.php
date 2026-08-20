<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Bridge\Cloudflare\Transport;

use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;

/**
 * @author vadage
 */
final class CloudflareSmtpTransport extends EsmtpTransport
{
    public function __construct(#[\SensitiveParameter] string $apiToken, ?EventDispatcherInterface $dispatcher = null, ?LoggerInterface $logger = null)
    {
        parent::__construct('smtp.mx.cloudflare.net', 465, true, $dispatcher, $logger);

        $this->setUsername('api_token');
        $this->setPassword($apiToken);
    }
}
