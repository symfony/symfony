<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Core\Tests\Authorization\Voter;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;
use Symfony\Component\Security\Core\Exception\InvalidArgumentException;

class VoteTest extends TestCase
{
    public function testMessages()
    {
        $vote = new Vote(VoterInterface::ACCESS_GRANTED, 'foo');

        $this->assertSame('foo', $vote->getMessage());

        $vote->addMessage('bar');
        $this->assertSame('foo, bar', $vote->getMessage());
    }

    public function testMessagesWithNotString()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Message must be string, "bool" given.');

        new Vote(VoterInterface::ACCESS_GRANTED, ['foo', true]);
    }
}
