<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpFoundation\Tests\Session;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Attribute\AttributeBag;
use Symfony\Component\HttpFoundation\Session\SessionFactory;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\HttpFoundation\Session\Storage\SessionStorageFactoryInterface;
use Symfony\Component\HttpFoundation\Session\Storage\SessionStorageInterface;

class SessionFactoryTest extends TestCase
{
    public function testCreateSessionWithAttributeBagFactory()
    {
        $requestStack = new RequestStack();
        $requestStack->push(new Request());
        $storageFactory = new class implements SessionStorageFactoryInterface {
            public function createStorage(?Request $request): SessionStorageInterface
            {
                return new MockArraySessionStorage();
            }
        };
        $bags = [];
        $factory = new SessionFactory($requestStack, $storageFactory, null, static function () use (&$bags) {
            return $bags[] = new AttributeBag('_sf2_attributes', true);
        });

        $session1 = $factory->createSession();
        $session2 = $factory->createSession();

        $this->assertCount(2, $bags);
        $this->assertSame($bags[0], $session1->getBag('attributes'));
        $this->assertSame($bags[1], $session2->getBag('attributes'));
    }
}
