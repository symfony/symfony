<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Transport;

use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\SentMessage;

/**
 * Sends the message to a file.
 *
 * @author Claudiu Cristea <clau.cristea@gmail.com>
 */
final class FileTransport extends AbstractTransport
{
    public function __construct(protected Dsn $dsn, EventDispatcherInterface $dispatcher = null, LoggerInterface $logger = null)
    {
        parent::__construct($dispatcher, $logger);
    }

    protected function doSend(SentMessage $message): void
    {
        if (false !== file_put_contents($this->getFile(), $message->toString())) {
            $this->getLogger()->debug(sprintf('Email sent with "%s" transport', $this->getFile()));

            return;
        }
        $this->getLogger()->error(sprintf('Cannot send email using "%s" transport', $this->getFile()));
    }

    public function __toString(): string
    {
        return $this->getFile();
    }

    private function getFile(): string
    {
        return $this->dsn->getScheme().'://'.$this->dsn->getPath();
    }
}
