<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Mattermost\Tests;

use Symfony\Component\Notifier\Bridge\Mattermost\MattermostTransportFactory;
use Symfony\Component\Notifier\Tests\TransportFactoryTestCase;
use Symfony\Component\Notifier\Transport\TransportFactoryInterface;

/**
 * @author Oskar Stark <oskarstark@googlemail.com>
 */
final class MattermostTransportFactoryTest extends TransportFactoryTestCase
{
    /**
     * @return MattermostTransportFactory
     */
    public function createFactory(): TransportFactoryInterface
    {
        return new MattermostTransportFactory();
    }

    public function createProvider(): iterable
    {
        yield [
            'mattermost://host.test?channel=testChannel',
            'mattermost://accessToken@host.test?channel=testChannel',
        ];

        yield [
            'mattermost://example.com/sub?channel=testChannel',
            'mattermost://accessToken@example.com/sub?channel=testChannel',
        ];

        yield [
            'mattermost://example.com/sub?channel=testChannel',
            'mattermost://accessToken@example.com/sub/?channel=testChannel',
        ];

        yield [
            'mattermost://example.com/sub/sub-2?channel=testChannel',
            'mattermost://accessToken@example.com/sub/sub-2?channel=testChannel',
        ];
    }

    public function supportsProvider(): iterable
    {
        yield [true, 'mattermost://token@host?channel=testChannel'];
        yield [false, 'somethingElse://token@host?channel=testChannel'];
    }

    public function incompleteDsnProvider(): iterable
    {
        yield 'missing option: token' => ['mattermost://host.test?channel=testChannel'];
        yield 'missing option: channel' => ['mattermost://token@host'];
    }

    public function unsupportedSchemeProvider(): iterable
    {
        yield ['somethingElse://token@host?channel=testChannel'];
        yield ['somethingElse://token@host']; // missing "channel" option
    }
}
