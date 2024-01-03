<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Twig\Tests\Mime;

use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Twig\Mime\NotificationEmail;

class NotificationEmailTest extends TestCase
{
    public function test()
    {
        $email = (new NotificationEmail())
            ->markdown('Foo')
            ->exception(new \Exception())
            ->importance(NotificationEmail::IMPORTANCE_HIGH)
            ->action('Bar', 'http://example.com/')
            ->context(['a' => 'b'])
        ;

        $this->assertEquals([
            'importance' => NotificationEmail::IMPORTANCE_HIGH,
            'content' => 'Foo',
            'exception' => true,
            'action_text' => 'Bar',
            'action_url' => 'http://example.com/',
            'markdown' => true,
            'raw' => false,
            'a' => 'b',
            'footer_text' => 'Notification email sent by Symfony',
        ], $email->getContext());
    }

    public function testSerialize()
    {
        $email = unserialize(serialize((new NotificationEmail())
            ->content('Foo', true)
            ->exception(new \Exception())
            ->importance(NotificationEmail::IMPORTANCE_HIGH)
            ->action('Bar', 'http://example.com/')
            ->context(['a' => 'b'])
            ->theme('example')
        ));
        $this->assertEquals([
            'importance' => NotificationEmail::IMPORTANCE_HIGH,
            'content' => 'Foo',
            'exception' => true,
            'action_text' => 'Bar',
            'action_url' => 'http://example.com/',
            'markdown' => false,
            'raw' => true,
            'a' => 'b',
            'footer_text' => 'Notification email sent by Symfony',
        ], $email->getContext());

        $this->assertSame('@email/example/notification/body.html.twig', $email->getHtmlTemplate());
    }

    public function testTheme()
    {
        $email = (new NotificationEmail())->theme('mine');
        $this->assertSame('@email/mine/notification/body.html.twig', $email->getHtmlTemplate());
        $this->assertSame('@email/mine/notification/body.txt.twig', $email->getTextTemplate());
    }

    public function testSubject()
    {
        $email = (new NotificationEmail())->from('me@example.com')->subject('Foo');
        $headers = $email->getPreparedHeaders();
        $this->assertSame('[LOW] Foo', $headers->get('Subject')->getValue());
    }

    public function testPublicMail()
    {
        $email = NotificationEmail::asPublicEmail()
            ->markdown('Foo')
            ->action('Bar', 'http://example.com/')
            ->context(['a' => 'b'])
        ;

        $this->assertEquals([
            'importance' => null,
            'content' => 'Foo',
            'exception' => false,
            'action_text' => 'Bar',
            'action_url' => 'http://example.com/',
            'markdown' => true,
            'raw' => false,
            'a' => 'b',
            'footer_text' => null,
        ], $email->getContext());

        $email = (new NotificationEmail())
            ->markAsPublic()
            ->markdown('Foo')
            ->action('Bar', 'http://example.com/')
            ->context(['a' => 'b'])
        ;

        $this->assertEquals([
            'importance' => null,
            'content' => 'Foo',
            'exception' => false,
            'action_text' => 'Bar',
            'action_url' => 'http://example.com/',
            'markdown' => true,
            'raw' => false,
            'a' => 'b',
            'footer_text' => null,
        ], $email->getContext());
    }

    public function testPublicMailSubject()
    {
        $email = NotificationEmail::asPublicEmail()->from('me@example.com')->subject('Foo');
        $headers = $email->getPreparedHeaders();
        $this->assertSame('Foo', $headers->get('Subject')->getValue());

        $email = (new NotificationEmail())->markAsPublic()->from('me@example.com')->subject('Foo');
        $headers = $email->getPreparedHeaders();
        $this->assertSame('Foo', $headers->get('Subject')->getValue());
    }
}
