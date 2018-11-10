<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Tests\Handler;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Handler\HandlersLocator;
use Symfony\Component\Messenger\Tests\Fixtures\DummyMessage;

class HandlersLocatorTest extends TestCase
{
    public function testItYieldsProvidedAliasAsKey()
    {
        $handler = $this->createPartialMock(\stdClass::class, array('__invoke'));
        $locator = new HandlersLocator(array(
            DummyMessage::class => array('dummy' => $handler),
        ));

        $this->assertSame(array('dummy' => $handler), iterator_to_array($locator->getHandlers(new Envelope(new DummyMessage('a')))));
    }
}
