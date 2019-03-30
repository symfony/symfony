<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Bridge\Google\Smtp;

use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;

/**
 * @author Kevin Verschaeve
 *
 * @experimental in 4.3
 */
class GmailTransport extends EsmtpTransport
{
    public function __construct(string $username, string $password, EventDispatcherInterface $dispatcher = null, LoggerInterface $logger = null)
    {
        parent::__construct('smtp.gmail.com', 465, 'ssl', null, $dispatcher, $logger);

        $this->setUsername($username);
        $this->setPassword($password);
    }
}
