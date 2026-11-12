<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Instagram\Tests;

use Symfony\Component\Notifier\Bridge\Instagram\InstagramTransportFactory;
use Symfony\Component\Notifier\Exception\InvalidArgumentException;
use Symfony\Component\Notifier\Test\AbstractTransportFactoryTestCase;
use Symfony\Component\Notifier\Test\IncompleteDsnTestTrait;
use Symfony\Component\Notifier\Test\MissingRequiredOptionTestTrait;
use Symfony\Component\Notifier\Transport\Dsn;

/**
 * @author Mathieu Ledru <matyo91@gmail.com>
 */
final class InstagramTransportFactoryTest extends AbstractTransportFactoryTestCase
{
    use IncompleteDsnTestTrait;
    use MissingRequiredOptionTestTrait;

    public function createFactory(): InstagramTransportFactory
    {
        return new InstagramTransportFactory();
    }

    public function testCreateWithPollOptions()
    {
        $transport = $this->createFactory()->create(new Dsn('instagram://token@default?user_id=17841400000000000&poll_attempts=3&poll_delay=0.5'));

        $this->assertSame(3, (new \ReflectionProperty($transport, 'pollAttempts'))->getValue($transport));
        $this->assertSame(0.5, (new \ReflectionProperty($transport, 'pollDelay'))->getValue($transport));
    }

    public function testCreateWithInvalidPollOptions()
    {
        $cases = [
            'poll_attempts=abc' => 'Option "poll_attempts" of the Instagram Notifier transport must be an integer.',
            'poll_attempts=1.5' => 'Option "poll_attempts" of the Instagram Notifier transport must be an integer.',
            'poll_delay=2s' => 'Option "poll_delay" of the Instagram Notifier transport must be a number.',
        ];

        foreach ($cases as $query => $message) {
            try {
                $this->createFactory()->create(new Dsn('instagram://token@default?user_id=17841400000000000&'.$query));
                $this->fail(\sprintf('"%s" should have been rejected.', $query));
            } catch (InvalidArgumentException $e) {
                $this->assertSame($message, $e->getMessage());
            }
        }
    }

    public static function createProvider(): iterable
    {
        yield [
            'instagram://graph.instagram.com?user_id=17841400000000000&api_version=v22.0',
            'instagram://token@default?user_id=17841400000000000&api_version=v22.0',
        ];
    }

    public static function supportsProvider(): iterable
    {
        yield [true, 'instagram://token@host.test?user_id=17841400000000000'];
        yield [false, 'somethingElse://token@host.test?user_id=17841400000000000'];
    }

    public static function unsupportedSchemeProvider(): iterable
    {
        yield ['somethingElse://token@host.test?user_id=17841400000000000'];
    }

    public static function incompleteDsnProvider(): iterable
    {
        yield 'missing token' => ['instagram://host.test?user_id=17841400000000000'];
    }

    public static function missingRequiredOptionProvider(): iterable
    {
        yield 'missing option: user_id' => ['instagram://token@host.test'];
    }
}
