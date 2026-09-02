<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Mailer\RemoteTemplateEmail;
use Symfony\Component\Mime\Exception\LogicException;
use Symfony\Component\Mime\Part\TextPart;

class RemoteTemplateEmailTestToStringGadget
{
    public static bool $fired = false;

    public function __toString(): string
    {
        self::$fired = true;

        return '';
    }
}

class RemoteTemplateEmailTest extends TestCase
{
    public function testTemplate()
    {
        $email = new RemoteTemplateEmail();
        $this->assertNull($email->getRemoteTemplate());

        $email->template('welcome', ['firstName' => 'Fabien']);
        $this->assertSame('welcome', $email->getRemoteTemplate()->getReference());
        $this->assertSame(['firstName' => 'Fabien'], $email->getRemoteTemplate()->getVariables());

        $email->template(null);
        $this->assertNull($email->getRemoteTemplate());
    }

    public function testEnsureValidityWithTemplateAndNoBody()
    {
        $email = new RemoteTemplateEmail();
        $email->from('fabien@symfony.com');
        $email->to('you@example.com');
        $email->template('welcome');

        $email->ensureValidity();

        $this->addToAssertionCount(1);
    }

    public function testEnsureValidityWithTemplateAndTextBody()
    {
        $email = new RemoteTemplateEmail();
        $email->from('fabien@symfony.com');
        $email->to('you@example.com');
        $email->template('welcome');
        $email->text('some text');

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('An email using a remote template cannot have a text or an HTML part; its body is rendered by the mail provider.');

        $email->ensureValidity();
    }

    public function testEnsureValidityWithTemplateAndHtmlBody()
    {
        $email = new RemoteTemplateEmail();
        $email->from('fabien@symfony.com');
        $email->to('you@example.com');
        $email->template('welcome');
        $email->html('<b>some html</b>');

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('An email using a remote template cannot have a text or an HTML part; its body is rendered by the mail provider.');

        $email->ensureValidity();
    }

    public function testEnsureValidityWithoutTemplateStillRequiresABody()
    {
        $email = new RemoteTemplateEmail();
        $email->from('fabien@symfony.com');
        $email->to('you@example.com');

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('A message must have a text or an HTML part or attachments.');

        $email->ensureValidity();
    }

    public function testGetBodyReturnsAPlaceholderWhenATemplateIsSet()
    {
        $email = new RemoteTemplateEmail();
        $email->template('welcome');

        $body = $email->getBody();

        $this->assertInstanceOf(TextPart::class, $body);
        $this->assertStringContainsString('welcome', $body->getBody());
    }

    public function testSerialization()
    {
        $email = new RemoteTemplateEmail();
        $email->from('fabien@symfony.com');
        $email->to('you@example.com');
        $email->template('welcome', ['firstName' => 'Fabien']);

        $email = unserialize(serialize($email));

        $this->assertSame('welcome', $email->getRemoteTemplate()->getReference());
        $this->assertSame(['firstName' => 'Fabien'], $email->getRemoteTemplate()->getVariables());
        $this->assertSame('fabien@symfony.com', $email->getFrom()[0]->getAddress());
    }

    public function testUnserializeRejectsInvalidTemplateSlot()
    {
        $email = new RemoteTemplateEmail();
        $email->template('welcome');
        $data = $email->__serialize();
        $data[0] = new RemoteTemplateEmailTestToStringGadget();
        $payload = \sprintf('O:%d:"%s":%d:{', \strlen(RemoteTemplateEmail::class), RemoteTemplateEmail::class, \count($data));
        foreach ($data as $key => $value) {
            $payload .= serialize($key).serialize($value);
        }
        $payload .= '}';
        RemoteTemplateEmailTestToStringGadget::$fired = false;

        try {
            unserialize($payload);
            $this->fail('Expected BadMethodCallException.');
        } catch (\BadMethodCallException $e) {
        }

        $this->assertFalse(RemoteTemplateEmailTestToStringGadget::$fired, '__toString gadget must not fire during unserialize');
    }
}
