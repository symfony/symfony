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

use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Pretends messages have been sent, but just ignores them.
 *
 * @author Hugo Alliaume <@kocal>
 */
final class FileTransport extends AbstractTransport
{
    private $path;

    public function __construct(string $path, EventDispatcherInterface $dispatcher = null, LoggerInterface $logger = null)
    {
        parent::__construct($dispatcher, $logger);
        $this->path = $path;
        if (!file_exists($this->path)) {
            if (!mkdir($this->path, 0777, true)) {
                throw new \RuntimeException(sprintf('Unable to create path "%s".', $this->path));
            }
        }
    }

    protected function doSend(SentMessage $message): void
    {
        $file = $this->path.'/'.uniqid().'.message';
        $serializedMessage = serialize($message);
        if (false === file_put_contents($file, $serializedMessage)) {
            throw new \RuntimeException(sprintf('Unable to write sent message in file "%s".', $file));
        }
    }
}
