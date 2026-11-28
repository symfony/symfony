<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Tests\Dynamic;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Dynamic\DynamicField;
use Symfony\Component\Form\Dynamic\DynamicFieldConfig;
use Symfony\Component\Form\FormEvents;

class DynamicFieldConfigTest extends TestCase
{
    public function testIsReadyReturnsCorrectlyBasedOnDependencies()
    {
        $fieldConfig = new DynamicFieldConfig('state', ['country'], static fn () => null);

        $this->assertFalse($fieldConfig->isReady([], FormEvents::PRE_SET_DATA));
        $this->assertTrue($fieldConfig->isReady(['country' => 'United States'], FormEvents::PRE_SET_DATA));
        $this->assertTrue($fieldConfig->isReady(['country' => 'United States'], FormEvents::POST_SUBMIT));
        $this->assertTrue($fieldConfig->isReady(['country' => 'United States', 'extra' => 'field'], FormEvents::POST_SUBMIT));
    }

    public function testIsReadyReturnsFalseIfCallbackExecuted()
    {
        $fieldConfig = new DynamicFieldConfig('state', ['country'], static fn () => null);

        $this->assertTrue($fieldConfig->isReady(['country' => 'United States'], FormEvents::PRE_SET_DATA));

        $fieldConfig->execute(['country' => 'United States'], FormEvents::PRE_SET_DATA);

        $this->assertFalse($fieldConfig->isReady(['country' => 'United States'], FormEvents::PRE_SET_DATA));
        $this->assertTrue($fieldConfig->isReady(['country' => 'United States'], FormEvents::POST_SUBMIT));
    }

    public function testIsReadyThrowsOnInvalidEventName()
    {
        $fieldConfig = new DynamicFieldConfig('state', ['country'], static fn () => null);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid event name "foo".');

        $fieldConfig->isReady(['country' => 'United States'], 'foo');
    }

    public function testExecuteCallsCallback()
    {
        $argsPassedToCallback = null;
        $fieldConfig = new DynamicFieldConfig('state', ['country', 'shouldHideRandomStates'], static function ($dynamicField, $country, $shouldHideRandomStates) use (&$argsPassedToCallback) {
            $argsPassedToCallback = [$dynamicField, $country, $shouldHideRandomStates];
        });

        $fieldConfig->execute(['country' => 'United States', 'shouldHideRandomStates' => true], FormEvents::PRE_SET_DATA);

        $this->assertNotNull($argsPassedToCallback);
        $this->assertInstanceOf(DynamicField::class, $argsPassedToCallback[0]);
        $this->assertSame('United States', $argsPassedToCallback[1]);
        $this->assertTrue($argsPassedToCallback[2]);
    }
}
