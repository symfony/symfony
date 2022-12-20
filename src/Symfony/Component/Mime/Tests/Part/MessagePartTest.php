<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mime\Tests\Part;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Header\Headers;
use Symfony\Component\Mime\Header\ParameterizedHeader;
use Symfony\Component\Mime\Header\UnstructuredHeader;
use Symfony\Component\Mime\Part\MessagePart;

class MessagePartTest extends TestCase
{
    public function testConstructor()
    {
        $p = new MessagePart((new Email())->from('fabien@symfony.com')->to('you@example.com')->text('content'));
        self::assertStringContainsString('content', $p->getBody());
        self::assertStringContainsString('content', $p->bodyToString());
        self::assertStringContainsString('content', implode('', iterator_to_array($p->bodyToIterable())));
        self::assertEquals('message', $p->getMediaType());
        self::assertEquals('rfc822', $p->getMediaSubType());
    }

    public function testHeaders()
    {
        $p = new MessagePart((new Email())->from('fabien@symfony.com')->text('content')->subject('Subject'));
        self::assertEquals(new Headers(
            new ParameterizedHeader('Content-Type', 'message/rfc822', ['name' => 'Subject.eml']),
            new UnstructuredHeader('Content-Transfer-Encoding', 'base64'),
            new ParameterizedHeader('Content-Disposition', 'attachment', ['name' => 'Subject.eml', 'filename' => 'Subject.eml'])
        ), $p->getPreparedHeaders());
    }

    public function testSerialize()
    {
        $email = (new Email())->from('fabien@symfony.com')->to('you@example.com')->text('content');
        $email->getHeaders()->addIdHeader('Message-ID', $email->generateMessageId());

        $p = new MessagePart($email);
        $expected = clone $p;
        self::assertEquals($expected->toString(), unserialize(serialize($p))->toString());
    }
}
