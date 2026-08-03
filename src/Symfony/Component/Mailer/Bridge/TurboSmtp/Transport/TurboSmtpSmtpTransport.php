<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Bridge\TurboSmtp\Transport;

use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;

/**
 * @author Dominik Spitzli <dominik@spitzli.dev>
 */
final class TurboSmtpSmtpTransport extends EsmtpTransport
{
    public function __construct(string $host, #[\SensitiveParameter] string $username, #[\SensitiveParameter] string $password, ?EventDispatcherInterface $dispatcher = null, ?LoggerInterface $logger = null)
    {
        parent::__construct($host, 587, false, $dispatcher, $logger);

        $this->setUsername($username);
        $this->setPassword($password);
    }
}
