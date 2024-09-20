<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mime\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Message;
use Symfony\Component\Mime\MessageConverter;
use Symfony\Component\Mime\Part\DataPart;

class MessageConverterTest extends TestCase
{
    public function testToEmail()
    {
        $file = file_get_contents(__DIR__.'/Fixtures/mimetypes/test.gif');
        $email = (new Email())->from('fabien@symfony.com')->to('you@example.com');
        $this->assertSame($email, MessageConverter::toEmail($email));

        $this->assertConversion((clone $email)->text('text content'));
        $this->assertConversion((clone $email)->html('HTML content <img src="cid:test.jpg" />'));
        $this->assertConversion((clone $email)
            ->text('text content')
            ->html('HTML content <img src="cid:test.jpg" />')
        );
        $this->assertConversion((clone $email)
            ->text('text content')
            ->html('HTML content <img src="cid:test.jpg" />')
            ->addPart((new DataPart($file, 'test.jpg', 'image/gif'))->asInline())
        );
        $this->assertConversion((clone $email)
            ->text('text content')
            ->html('HTML content <img src="cid:test.jpg" />')
            ->addPart(new DataPart($file, 'test_attached.jpg', 'image/gif'))
        );
        $this->assertConversion((clone $email)
            ->text('text content')
            ->html('HTML content <img src="cid:test.jpg" />')
            ->addPart((new DataPart($file, 'test.jpg', 'image/gif'))->asInline())
            ->addPart(new DataPart($file, 'test_attached.jpg', 'image/gif'))
        );
        $this->assertConversion((clone $email)
            ->text('text content')
            ->addPart(new DataPart($file, 'test_attached.jpg', 'image/gif'))
        );
        $this->assertConversion((clone $email)
            ->html('HTML content <img src="cid:test.jpg" />')
            ->addPart(new DataPart($file, 'test_attached.jpg', 'image/gif'))
        );
        $this->assertConversion((clone $email)
            ->html('HTML content <img src="cid:test.jpg" />')
            ->addPart((new DataPart($file, 'test.jpg', 'image/gif'))->asInline())
        );
        $this->assertConversion((clone $email)
            ->text('text content')
            ->addPart((new DataPart($file, 'test_attached.jpg', 'image/gif'))->asInline())
        );
    }

    private function assertConversion(Email $expected)
    {
        $r = new \ReflectionMethod($expected, 'generateBody');

        $message = new Message($expected->getHeaders(), $r->invoke($expected));
        $converted = MessageConverter::toEmail($message);
        if ($expected->getHtmlBody()) {
            $this->assertStringMatchesFormat(str_replace('cid:test.jpg', 'cid:%s', $expected->getHtmlBody()), $converted->getHtmlBody());
            $expected->html('HTML content');
            $converted->html('HTML content');
        }

        $r = new \ReflectionProperty($expected, 'cachedBody');
        $r->setValue($expected, null);
        $r->setValue($converted, null);

        $this->assertEquals($expected, $converted);
    }
}
