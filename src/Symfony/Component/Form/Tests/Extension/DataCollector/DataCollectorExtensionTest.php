<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Tests\Extension\DataCollector;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Form\Extension\DataCollector\DataCollectorExtension;

/**
 * @covers Symfony\Component\Form\Extension\DataCollector\DataCollectorExtension
 */
class DataCollectorExtensionTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var DataCollectorExtension
     */
    private $extension;

    /**
     * @var EventSubscriberInterface
     */
    private $eventSubscriber;

    public function setUp()
    {
        $this->eventSubscriber = $this->getMock('Symfony\Component\EventDispatcher\EventSubscriberInterface');
        $this->extension = new DataCollectorExtension($this->eventSubscriber);
    }

    public function testLoadTypeExtensions()
    {
        $typeExtensions = $this->extension->getTypeExtensions('form');

        $this->assertInternalType('array', $typeExtensions);
        $this->assertCount(1, $typeExtensions);
        $this->assertInstanceOf('Symfony\Component\Form\Extension\DataCollector\Type\DataCollectorTypeExtension', array_shift($typeExtensions));
    }
}
 