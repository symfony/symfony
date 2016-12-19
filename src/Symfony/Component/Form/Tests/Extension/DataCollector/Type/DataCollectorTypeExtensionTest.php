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
        $this->dataCollector = $this->getMockBuilder('Symfony\Component\Form\Extension\DataCollector\FormDataCollectorInterface')->getMock();
        $this->extension = new DataCollectorTypeExtension($this->dataCollector);
    }

    public function testGetExtendedType()
    {
        $this->assertEquals('form', $this->extension->getExtendedType());
    }

    public function testBuildForm()
    {
        $builder = $this->getMockBuilder('Symfony\Component\Form\Test\FormBuilderInterface')->getMock();
        $builder->expects($this->atLeastOnce())
            ->method('addEventSubscriber')
            ->with($this->isInstanceOf('Symfony\Component\Form\Extension\DataCollector\EventListener\DataCollectorListener'));

        $this->extension->buildForm($builder, array());
    }
}
