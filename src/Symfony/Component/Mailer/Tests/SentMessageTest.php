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
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\RawMessage;

class SentMessageTest extends TestCase
{
    public function test()
    {
        $m = new SentMessage($r = new RawMessage('Email'), $e = new Envelope(new Address('fabien@example.com'), [new Address('helene@example.com')]));
        $this->assertSame($r, $m->getOriginalMessage());
        $this->assertSame($r, $m->getMessage());
        $this->assertSame($e, $m->getEnvelope());
        $this->assertEquals($r->toString(), $m->toString());
        $this->assertEquals($r->toIterable(), $m->toIterable());

        $m = new SentMessage($r = (new Email())->from('fabien@example.com')->to('helene@example.com')->text('text'), $e);
        $this->assertSame($r, $m->getOriginalMessage());
        $this->assertNotSame($r, $m->getMessage());
    }
}
