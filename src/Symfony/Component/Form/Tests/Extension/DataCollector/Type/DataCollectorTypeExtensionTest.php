<?php
/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Tests\Extension\DataCollector\Type;

use Symfony\Component\Form\Extension\DataCollector\Type\DataCollectorTypeExtension;

class DataCollectorTypeExtensionTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var DataCollectorTypeExtension
     */
    private $extension;

    /**
     * @var \PHPUnit_Framework_MockObject_MockObject
     */
    private $dataCollector;

    protected function setUp()
    {
        $this->dataCollector = $this->getMock('Symfony\Component\Form\Extension\DataCollector\FormDataCollectorInterface');
        $this->extension = new DataCollectorTypeExtension($this->dataCollector);
    }

    public function testGetExtendedType()
    {
        $this->assertEquals('form', $this->extension->getExtendedType());
    }

    public function testGetExtendedTypes()
    {
        $types = $this->extension->getExtendedTypes();

        $this->assertInternalType('array', $types);
        $this->assertArrayHasKey(0, $types);
        $this->assertEquals($this->extension->getExtendedType(), $types[0]);
    }

    public function testBuildForm()
    {
        $builder = $this->getMock('Symfony\Component\Form\Test\FormBuilderInterface');
        $builder->expects($this->atLeastOnce())
            ->method('addEventSubscriber')
            ->with($this->isInstanceOf('Symfony\Component\Form\Extension\DataCollector\EventListener\DataCollectorListener'));

        $this->extension->buildForm($builder, array());
    }
}
