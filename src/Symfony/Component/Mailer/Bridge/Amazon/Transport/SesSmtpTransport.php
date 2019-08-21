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
use Symfony\Component\Mailer\Bridge\Amazon\Credential\UsernamePasswordCredential;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @author Kevin Verschaeve
 */
class SesSmtpTransport extends EsmtpTransport
{
    /**
     * @param UsernamePasswordCredential $credential credential object for SES authentication
     * @param string                     $region     Amazon SES region (currently one of us-east-1, us-west-2, or eu-west-1)
     */
    public function __construct(UsernamePasswordCredential $credential, string $region = null, EventDispatcherInterface $dispatcher = null, LoggerInterface $logger = null)
    {
        parent::__construct(sprintf('email-smtp.%s.amazonaws.com', $region ?: 'eu-west-1'), 587, true, $dispatcher, $logger);

        $this->setUsername($credential->getUsername());
        $this->setPassword($credential->getPassword());
    }
}
