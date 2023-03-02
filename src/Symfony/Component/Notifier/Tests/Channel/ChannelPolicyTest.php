<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Tests\Channel;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Notifier\Channel\ChannelPolicy;
use Symfony\Component\Notifier\Exception\InvalidArgumentException;

/**
 * @author Jan Schädlich <jan.schaedlich@sensiolabs.de>
 */
class ChannelPolicyTest extends TestCase
{
    public function testCannotRetrieveChannelsUsingUnavailableImportance()
    {
        $this->expectException(InvalidArgumentException::class);

        $channelPolicy = new ChannelPolicy(['urgent' => ['chat']]);
        $channelPolicy->getChannels('low');
    }

    /**
     * @dataProvider provideValidPolicies
     */
    public function testCanRetrieveChannels(array $policy, string $importance, array $expectedChannels)
    {
        $channelPolicy = new ChannelPolicy($policy);
        $channels = $channelPolicy->getChannels($importance);

        $this->assertSame($expectedChannels, $channels);
    }

    public static function provideValidPolicies(): \Generator
    {
        yield [['urgent' => ['chat']], 'urgent', ['chat']];
        yield [['urgent' => ['chat', 'sms']], 'urgent', ['chat', 'sms']];
        yield [['urgent' => ['chat', 'chat/slack', 'sms']], 'urgent', ['chat', 'chat/slack', 'sms']];
    }
}
