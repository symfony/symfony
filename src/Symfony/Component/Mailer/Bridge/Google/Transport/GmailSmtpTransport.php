<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Bridge\Google\Transport;

use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;

/**
 * @author Kevin Verschaeve
 */
class GmailSmtpTransport extends EsmtpTransport
{
    public function __construct(string $username, #[\SensitiveParameter] string $password, EventDispatcherInterface $dispatcher = null, LoggerInterface $logger = null)
    {
        parent::__construct('smtp.gmail.com', 465, true, $dispatcher, $logger);

        $this->setUsername($username);
        $this->setPassword($password);
    }
}
