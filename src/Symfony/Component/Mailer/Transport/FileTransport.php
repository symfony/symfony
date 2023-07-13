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
 * @author Fabien Potencier <fabien@symfony.com>
 */
final class FileTransport extends AbstractTransport
{
    public function __construct(EventDispatcherInterface $dispatcher = null, LoggerInterface $logger = null, protected Dsn $dsn)
    {
        parent::__construct($dispatcher, $logger);
    }

    protected function doSend(SentMessage $message): void
    {
        file_put_contents($this->getFile(), $message->toString());
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
