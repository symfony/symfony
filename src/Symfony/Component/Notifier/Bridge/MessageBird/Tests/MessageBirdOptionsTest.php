<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\MessageBird\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Notifier\Bridge\MessageBird\MessageBirdOptions;

final class MessageBirdOptionsTest extends TestCase
{
    public function testGetRecipientIdWhenSet()
    {
        $messagebirdOptions = new MessageBirdOptions([
            'recipients' => 'foo',
        ]);

        $this->assertSame('foo', $messagebirdOptions->getRecipientId());
    }

    public function testGetRecipientIdWhenNotSet()
    {
        $this->assertNull((new MessageBirdOptions())->getRecipientId());
    }

    public function testSetValidity()
    {
        $messagebirdOptions = new MessageBirdOptions();

        $messagebirdOptions->validity(500);

        $this->assertSame(500, $messagebirdOptions->toArray()['validity']);
    }

    public function testSetReference()
    {
        $messagebirdOptions = new MessageBirdOptions();

        $messagebirdOptions->reference('foo');

        $this->assertSame('foo', $messagebirdOptions->toArray()['reference']);
    }
}
