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
use Symfony\Component\Notifier\Bridge\Chatwork\ChatworkMessageBodyBuilder;

class ChatworkMessageBodyBuilderTest extends TestCase
{
    public function testSetTo()
    {
        $builder = new ChatworkMessageBodyBuilder();
        $builder->to(['abc', 'def']);
        $property = new \ReflectionProperty($builder, 'to');
        $property->setAccessible(true);
        $this->assertSame(['abc', 'def'], $property->getValue($builder));
        $builder->to('ghi');
        $this->assertSame(['ghi'], $property->getValue($builder));
    }

    public function testSetSelfUnread()
    {
        $builder = new ChatworkMessageBodyBuilder();
        $builder->selfUnread(true);
        $property = new \ReflectionProperty($builder, 'selfUnread');
        $property->setAccessible(true);
        $this->assertTrue($property->getValue($builder));
    }

    public function testSetBody()
    {
        $builder = new ChatworkMessageBodyBuilder();
        $builder->body('test body');
        $property = new \ReflectionProperty($builder, 'body');
        $property->setAccessible(true);
        $this->assertEquals('test body', $property->getValue($builder));
    }

    public function testGetMessageBody()
    {
        $builder = new ChatworkMessageBodyBuilder();
        $builder
            ->to(['abc', 'def'])
            ->selfUnread(true)
            ->body('test body')
        ;

        $expectedBody = <<<EOT
[To:abc]
[To:def]
test body
EOT;
        $expected = [
            'body' => $expectedBody,
            'self_unread' => true,
        ];
        $this->assertEquals($expected, $builder->getMessageBody());
    }
}
