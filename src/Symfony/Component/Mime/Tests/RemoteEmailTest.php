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
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Exception\LogicException;
use Symfony\Component\Mime\RemoteEmail;

/**
 * @author Mounir Mouih <mounir.mouih@gmail.com>
 */
class RemoteEmailTest extends TestCase
{
    public function testBody()
    {
        $email = new RemoteEmail();
        $this->assertEquals('', $email->getBody()->bodyToString());
    }

    public function testInvalidEmail()
    {
        $email = new RemoteEmail();
        $email->subject('Remote Email Subject !')
            ->to(new Address('mounir.mouih@gmail.com', 'Mounir Mouih'))
            ->from(new Address('fabpot@symfony.com', 'Fabien'))
            ->addCc('foo@bar.fr')
            ->addBcc('foo@bar.fr')
            ->addReplyTo('foo@bar.fr');

        $this->expectException(LogicException::class);
        $email->ensureValidity();
    }

    public function testvalidEmail()
    {
        $email = new RemoteEmail();
        $email->subject('Remote Email Subject !')
            ->to(new Address('mounir.mouih@gmail.com', 'Mounir Mouih'))
            ->from(new Address('fabpot@symfony.com', 'Fabien'))
            ->addCc('foo@bar.fr')
            ->addBcc('foo@bar.fr')
            ->addReplyTo('foo@bar.fr');

        $email->setRemoteTemplate('templateId', '1');
        $email->ensureValidity();
        $this->assertTrue(true);
    }

    public function testSetTemplate()
    {
        $email = new RemoteEmail();
        $email->subject('Remote Email Subject !')
            ->to(new Address('mounir.mouih@gmail.com', 'Mounir Mouih'))
            ->from(new Address('fabpot@symfony.com', 'Fabien'))
            ->addCc('foo@bar.fr')
            ->addBcc('foo@bar.fr')
            ->addReplyTo('foo@bar.fr');

        $email->setRemoteTemplate('templateId', '1');
        $email->ensureValidity();

        $this->assertSame('templateId', $email->getTemplateHeaderName());
        $this->assertSame('1', $email->getTemplateId());
    }

    public function testConsecutiveSetTemplateCalls()
    {
        $email = new RemoteEmail();
        $email->subject('Remote Email Subject !')
            ->to(new Address('mounir.mouih@gmail.com', 'Mounir Mouih'))
            ->from(new Address('fabpot@symfony.com', 'Fabien'))
            ->addCc('foo@bar.fr')
            ->addBcc('foo@bar.fr')
            ->addReplyTo('foo@bar.fr');

        $email->setRemoteTemplate('templateId', '1');
        $email->ensureValidity();
        $this->assertSame('templateId', $email->getTemplateHeaderName());
        $this->assertSame('1', $email->getTemplateId());

        // Change template name
        $email->setRemoteTemplate('templateId', '2');
        $this->assertSame('templateId', $email->getTemplateHeaderName());
        $this->assertSame('2', $email->getTemplateId());

        // Change template header name
        $email->setRemoteTemplate('template_name', 'my_template');
        $this->assertSame('template_name', $email->getTemplateHeaderName());
        $this->assertSame('my_template', $email->getTemplateId());
    }
}
