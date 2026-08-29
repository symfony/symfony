<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Bridge\MailKite\Transport;

use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;

/**
 * @author Gabe (MailKite) <bucabay@gmail.com>
 */
final class MailKiteSmtpTransport extends EsmtpTransport
{
    public function __construct(#[\SensitiveParameter] string $key, bool $tls = false, ?EventDispatcherInterface $dispatcher = null, ?LoggerInterface $logger = null)
    {
        parent::__construct('smtp.mailkite.dev', $tls ? 465 : 587, $tls, $dispatcher, $logger);

        // The relay authenticates on the API key alone and ignores the username
        $this->setUsername('mailkite');
        $this->setPassword($key);
    }
}
