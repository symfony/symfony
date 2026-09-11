<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Tests\Attribute;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Attribute\AsControllerAttributeListener;
use Symfony\Component\HttpKernel\Event\ControllerArgumentsEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class AsControllerAttributeListenerTest extends TestCase
{
    public function testTheKernelEventIsAliasedAndSuffixedWithTheAttributeClass()
    {
        $attribute = new AsControllerAttributeListener(ControllerArgumentsEvent::class, \stdClass::class);

        $this->assertSame(KernelEvents::CONTROLLER_ARGUMENTS.'.'.\stdClass::class, $attribute->event);
        $this->assertNull($attribute->priority);
    }

    public function testBeforeAndAfterReachTheParentAttribute()
    {
        $attribute = new AsControllerAttributeListener(KernelEvents::VIEW, \stdClass::class, 'onView', 5, 'app.first', [\ArrayObject::class]);

        $this->assertSame('onView', $attribute->method);
        $this->assertSame(5, $attribute->priority);
        $this->assertSame('app.first', $attribute->before);
        $this->assertSame([\ArrayObject::class], $attribute->after);
    }
}
