<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\LineBot\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Notifier\Bridge\LineBot\LineBotOptions;

final class LineBotOptionsTest extends TestCase
{
    public function testEmptyOptions()
    {
        $options = new LineBotOptions();

        $this->assertSame([], $options->toArray());
        $this->assertNull($options->getRecipientId());
    }

    public function testTo()
    {
        $options = new LineBotOptions();

        $returnedOptions = $options->to('testReceiver');

        $this->assertSame($options, $returnedOptions);
        $this->assertSame(['to' => 'testReceiver'], $options->toArray());
        $this->assertSame('testReceiver', $options->getRecipientId());
    }

    public function testConstructWithOptions()
    {
        $options = new LineBotOptions(['to' => 'testReceiver']);

        $this->assertSame(['to' => 'testReceiver'], $options->toArray());
        $this->assertSame('testReceiver', $options->getRecipientId());
    }
}
