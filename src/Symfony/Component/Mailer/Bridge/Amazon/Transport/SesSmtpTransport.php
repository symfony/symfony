<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Bridge\Amazon\Transport;

use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @author Kevin Verschaeve
 */
class SesSmtpTransport extends EsmtpTransport
{
    /**
     * @param string $region Amazon SES region (currently one of us-east-1, us-west-2, or eu-west-1)
     */
    public function __construct(string $username, string $password, string $region = null, EventDispatcherInterface $dispatcher = null, LoggerInterface $logger = null)
    {
        parent::__construct(sprintf('email-smtp.%s.amazonaws.com', $region ?: 'eu-west-1'), 465, true, $dispatcher, $logger);

        $this->setUsername($username);
        $this->setPassword($password);
    }
}
