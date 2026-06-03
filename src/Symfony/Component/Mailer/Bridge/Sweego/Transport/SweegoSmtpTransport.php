<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Bridge\Sweego\Transport;

use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;

/**
 * @author Mathieu Santostefano <msantostefano@proton.me>
 */
final class SweegoSmtpTransport extends EsmtpTransport
{
    public function __construct(string $host, int $port, string $login, #[\SensitiveParameter] string $password, ?EventDispatcherInterface $dispatcher = null, ?LoggerInterface $logger = null)
    {
        parent::__construct($host, $port, true, $dispatcher, $logger);

        $this->setUsername($login);
        $this->setPassword($password);
    }
}
