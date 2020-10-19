<?php

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
        ], $email->getContext());
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
}
