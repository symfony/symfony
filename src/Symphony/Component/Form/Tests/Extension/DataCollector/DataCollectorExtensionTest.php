<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\Form\Tests\Extension\DataCollector;

use PHPUnit\Framework\TestCase;
use Symphony\Component\Form\Extension\DataCollector\DataCollectorExtension;

class DataCollectorExtensionTest extends TestCase
{
    /**
     * @var DataCollectorExtension
     */
    private $extension;

    /**
     * @var \PHPUnit_Framework_MockObject_MockObject
     */
    private $dataCollector;

    protected function setUp()
    {
        $this->dataCollector = $this->getMockBuilder('Symphony\Component\Form\Extension\DataCollector\FormDataCollectorInterface')->getMock();
        $this->extension = new DataCollectorExtension($this->dataCollector);
    }

    public function testLoadTypeExtensions()
    {
        $typeExtensions = $this->extension->getTypeExtensions('Symphony\Component\Form\Extension\Core\Type\FormType');

        $this->assertInternalType('array', $typeExtensions);
        $this->assertCount(1, $typeExtensions);
        $this->assertInstanceOf('Symphony\Component\Form\Extension\DataCollector\Type\DataCollectorTypeExtension', array_shift($typeExtensions));
    }
}
