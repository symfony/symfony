<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Bridge\Mailgun\Transport;

use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;

/**
 * @author Kevin Verschaeve
 */
class MailgunSmtpTransport extends EsmtpTransport
{
    use MailgunHeadersTrait;

    public function __construct(string $username, #[\SensitiveParameter] string $password, string $region = null, EventDispatcherInterface $dispatcher = null, LoggerInterface $logger = null)
    {
        parent::__construct('us' !== ($region ?: 'us') ? sprintf('smtp.%s.mailgun.org', $region) : 'smtp.mailgun.org', 587, true, $dispatcher, $logger);

        $this->setUsername($username);
        $this->setPassword($password);
    }
}
