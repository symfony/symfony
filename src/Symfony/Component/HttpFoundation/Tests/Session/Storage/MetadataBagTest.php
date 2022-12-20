<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpFoundation\Tests\Session\Storage;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Session\Storage\MetadataBag;

/**
 * Test class for MetadataBag.
 *
 * @group time-sensitive
 */
class MetadataBagTest extends TestCase
{
    /**
     * @var MetadataBag
     */
    protected $bag;

    protected $array = [];

    protected function setUp(): void
    {
        self::setUp();
        $this->bag = new MetadataBag();
        $this->array = [MetadataBag::CREATED => 1234567, MetadataBag::UPDATED => 12345678, MetadataBag::LIFETIME => 0];
        $this->bag->initialize($this->array);
    }

    protected function tearDown(): void
    {
        $this->array = [];
        $this->bag = null;
        self::tearDown();
    }

    public function testInitialize()
    {
        $sessionMetadata = [];

        $bag1 = new MetadataBag();
        $bag1->initialize($sessionMetadata);
        self::assertGreaterThanOrEqual(time(), $bag1->getCreated());
        self::assertEquals($bag1->getCreated(), $bag1->getLastUsed());

        sleep(1);
        $bag2 = new MetadataBag();
        $bag2->initialize($sessionMetadata);
        self::assertEquals($bag1->getCreated(), $bag2->getCreated());
        self::assertEquals($bag1->getLastUsed(), $bag2->getLastUsed());
        self::assertEquals($bag2->getCreated(), $bag2->getLastUsed());

        sleep(1);
        $bag3 = new MetadataBag();
        $bag3->initialize($sessionMetadata);
        self::assertEquals($bag1->getCreated(), $bag3->getCreated());
        self::assertGreaterThan($bag2->getLastUsed(), $bag3->getLastUsed());
        self::assertNotEquals($bag3->getCreated(), $bag3->getLastUsed());
    }

    public function testGetSetName()
    {
        self::assertEquals('__metadata', $this->bag->getName());
        $this->bag->setName('foo');
        self::assertEquals('foo', $this->bag->getName());
    }

    public function testGetStorageKey()
    {
        self::assertEquals('_sf2_meta', $this->bag->getStorageKey());
    }

    public function testGetLifetime()
    {
        $bag = new MetadataBag();
        $array = [MetadataBag::CREATED => 1234567, MetadataBag::UPDATED => 12345678, MetadataBag::LIFETIME => 1000];
        $bag->initialize($array);
        self::assertEquals(1000, $bag->getLifetime());
    }

    public function testGetCreated()
    {
        self::assertEquals(1234567, $this->bag->getCreated());
    }

    public function testGetLastUsed()
    {
        self::assertLessThanOrEqual(time(), $this->bag->getLastUsed());
    }

    public function testClear()
    {
        $this->bag->clear();

        // the clear method has no side effects, we just want to ensure it doesn't trigger any exceptions
        self::addToAssertionCount(1);
    }

    public function testSkipLastUsedUpdate()
    {
        $bag = new MetadataBag('', 30);
        $timeStamp = time();

        $created = $timeStamp - 15;
        $sessionMetadata = [
            MetadataBag::CREATED => $created,
            MetadataBag::UPDATED => $created,
            MetadataBag::LIFETIME => 1000,
        ];
        $bag->initialize($sessionMetadata);

        self::assertEquals($created, $sessionMetadata[MetadataBag::UPDATED]);
    }

    public function testDoesNotSkipLastUsedUpdate()
    {
        $bag = new MetadataBag('', 30);
        $timeStamp = time();

        $created = $timeStamp - 45;
        $sessionMetadata = [
            MetadataBag::CREATED => $created,
            MetadataBag::UPDATED => $created,
            MetadataBag::LIFETIME => 1000,
        ];
        $bag->initialize($sessionMetadata);

        self::assertEquals($timeStamp, $sessionMetadata[MetadataBag::UPDATED]);
    }

    public function testLifetimeIsInt()
    {
        $sessionMetadata = [];

        $bag = new MetadataBag();
        $bag->initialize($sessionMetadata);

        self::assertIsInt($bag->getLifetime());
    }
}
