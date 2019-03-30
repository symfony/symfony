<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Bridge\Amazon\Smtp;

use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;

/**
 * @author Kevin Verschaeve
 *
 * @experimental in 4.3
 */
class SesTransport extends EsmtpTransport
{
    /**
     * @param string $region Amazon SES region (currently one of us-east-1, us-west-2, or eu-west-1)
     */
    public function __construct(string $username, string $password, string $region = null, EventDispatcherInterface $dispatcher = null, LoggerInterface $logger = null)
    {
        parent::__construct(\sprintf('email-smtp.%s.amazonaws.com', $region ?: 'eu-west-1'), 587, 'tls', null, $dispatcher, $logger);

        $this->setUsername($username);
        $this->setPassword($password);
    }
}
