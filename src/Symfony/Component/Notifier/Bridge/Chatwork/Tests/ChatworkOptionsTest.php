<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Chatwork\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Notifier\Bridge\Chatwork\ChatworkOptions;

class ChatworkOptionsTest extends TestCase
{
    public function testSetTo()
    {
        $options = new ChatworkOptions();
        $options->to(['abc', 'def']);
        $this->assertSame(['to' => ['abc', 'def']], $options->toArray());

        $options->to('ghi');
        $this->assertSame(['to' => 'ghi'], $options->toArray());
    }

    public function testSetSelfUnread()
    {
        $options = new ChatworkOptions();
        $options->selfUnread(true);
        $this->assertSame(['selfUnread' => true], $options->toArray());
    }
}
