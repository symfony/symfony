<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\EventDispatcher\ImmutableEventDispatcher;
use Symfony\Component\Form\ButtonBuilder;
use Symfony\Component\Form\Exception\BadMethodCallException;
use Symfony\Component\Form\Exception\InvalidArgumentException;
use Symfony\Component\Form\FormEvents;

/**
 * @author Alexander Cheprasov <cheprasov.84@ya.ru>
 */
class ButtonBuilderTest extends TestCase
{
    public function getValidNames()
    {
        return [
            ['reset'],
            ['submit'],
            ['foo'],
            ['0'],
            [0],
        ];
    }

    /**
     * @dataProvider getValidNames
     */
    public function testValidNames($name)
    {
        $this->assertInstanceOf('\Symfony\Component\Form\ButtonBuilder', new ButtonBuilder($name, new EventDispatcher()));
    }

    /**
     * @group legacy
     */
    public function testNameContainingIllegalCharacters()
    {
        $this->assertInstanceOf('\Symfony\Component\Form\ButtonBuilder', new ButtonBuilder('button[]', new EventDispatcher()));
    }

    /**
     * @group legacy
     */
    public function testNameStartingWithIllegalCharacters()
    {
        $this->assertInstanceOf('\Symfony\Component\Form\ButtonBuilder', new ButtonBuilder('Button'));
    }

    public function getInvalidNames()
    {
        return [
            [''],
            [false],
            [null],
        ];
    }

    /**
     * @dataProvider getInvalidNames
     */
    public function testInvalidNames($name)
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Buttons cannot have empty names.');
        new ButtonBuilder($name, new EventDispatcher());
    }

    /**
     * @dataProvider supportedEventsProvider
     */
    public function testSupportedEvents(string $event)
    {
        $buttonBuilder = new ButtonBuilder('foo', new EventDispatcher());

        $buttonBuilder->addEventListener($event, ['class', 'method']);

        $subscriber = new class() implements EventSubscriberInterface {
            public static $event = 'foo';

            /**
             * {@inheritdoc}
             */
            public static function getSubscribedEvents(): array
            {
                return [
                    self::$event => 'method',
                ];
            }
        };
        $subscriber::$event = $event;
        $buttonBuilder->addEventSubscriber($subscriber);

        $this->assertSame([
            ['class', 'method'],
            [$subscriber, 'method'],
        ], $buttonBuilder->getEventDispatcher()->getListeners($event));
    }

    public function supportedEventsProvider()
    {
        return [
            [FormEvents::POST_SET_DATA],
            [FormEvents::PRE_SUBMIT],
            [FormEvents::POST_SUBMIT],
        ];
    }

    /**
     * @dataProvider unsupportedEventsProvider
     */
    public function testUnsupportedEventsWithAListener(string $expectedMessage, string $event)
    {
        $this->expectException(BadMethodCallException::class);
        $this->expectExceptionMessage($expectedMessage);

        (new ButtonBuilder('foo', new EventDispatcher()))->addEventListener($event, []);
    }

    /**
     * @dataProvider unsupportedEventsProvider
     */
    public function testUnsupportedEventsWithASubscriber(string $expectedMessage, string $event)
    {
        $this->expectException(BadMethodCallException::class);
        $this->expectExceptionMessage($expectedMessage);

        $subscriber = new class() implements EventSubscriberInterface {
            public static $event = 'foo';

            /**
             * {@inheritdoc}
             */
            public static function getSubscribedEvents(): array
            {
                return [
                    self::$event => 'method',
                ];
            }
        };
        $subscriber::$event = $event;

        (new ButtonBuilder('foo', new EventDispatcher()))->addEventSubscriber($subscriber);
    }

    public function unsupportedEventsProvider()
    {
        return [
            ['Buttons do not support the "form.pre_set_data" form event. Use "form.post_set_data" instead.', FormEvents::PRE_SET_DATA],
            ['Buttons do not support the "form.submit" form event. Use "form.pre_submit" or "form.post_submit" instead.', FormEvents::SUBMIT],
        ];
    }

    public function testGetEventDispatcher()
    {
        $eventDispatcher = new EventDispatcher();
        $buttonBuilder = new ButtonBuilder('foo', $eventDispatcher);

        $this->assertSame($eventDispatcher, $buttonBuilder->getEventDispatcher());

        $eventDispatcher->addListener($event = 'foo.bar', $callable = function () {});

        $immutableEventDispatcher = $buttonBuilder->getFormConfig()->getEventDispatcher();

        $this->assertInstanceOf(ImmutableEventDispatcher::class, $immutableEventDispatcher);
        $this->assertSame([$callable], $immutableEventDispatcher->getListeners($event));
    }
}
