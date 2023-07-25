<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mime\Tests\Crypto;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Mime\Exception\RuntimeException;
use Symfony\Component\Mime\Message;
use Symfony\Component\Mime\RawMessage;

abstract class SMimeTestCase extends TestCase
{
    protected string $samplesDir;

    protected function setUp(): void
    {
        $this->samplesDir = str_replace('\\', '/', realpath(__DIR__.'/../').'/_data/');
    }

    protected function generateTmpFilename(): string
    {
        return stream_get_meta_data(tmpfile())['uri'];
    }

    protected function normalizeFilePath(string $path): string
    {
        if (!file_exists($path)) {
            throw new RuntimeException(sprintf('File does not exist: "%s"', $path));
        }

        return str_replace('\\', '/', realpath($path));
    }

    protected function iterableToString(iterable $iterable): string
    {
        $string = '';

        // Can't use iterator_to_array as the generators are merged internally,
        // leading to overwritten keys
        foreach ($iterable as $chunk) {
            $string .= $chunk;
        }

        return $string;
    }

    protected function assertMessageHeaders(Message $message, RawMessage $originalMessage): void
    {
        $messageString = $message->toString();
        self::assertStringNotContainsString('Bcc: ', $messageString, '', true);

        if (!$originalMessage instanceof Message) {
            return;
        }

        if ($originalMessage->getHeaders()->has('Bcc')) {
            self::assertEquals($originalMessage->getHeaders()->get('Bcc'), $message->getHeaders()->get('Bcc'));
        }

        if ($originalMessage->getHeaders()->has('Subject')) {
            self::assertEquals($originalMessage->getHeaders()->get('Subject'), $message->getPreparedHeaders()->get('Subject'));
            self::assertStringContainsString('Subject:', $messageString, '', true);
        }
    }
}
