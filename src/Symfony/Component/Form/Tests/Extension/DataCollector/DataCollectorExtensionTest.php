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

use Symfony\Component\Form\Extension\DataCollector\DataCollectorExtension;

class DataCollectorExtensionTest extends \PHPUnit_Framework_TestCase
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
        $this->dataCollector = $this->getMock('Symfony\Component\Form\Extension\DataCollector\FormDataCollectorInterface');
        $this->extension = new DataCollectorExtension($this->dataCollector);
    }

    public function testLoadTypeExtensions()
    {
        $typeExtensions = $this->extension->getTypeExtensions('Symfony\Component\Form\Extension\Core\Type\FormType');

        $this->assertInternalType('array', $typeExtensions);
        $this->assertCount(1, $typeExtensions);
        $this->assertInstanceOf('Symfony\Component\Form\Extension\DataCollector\Type\DataCollectorTypeExtension', array_shift($typeExtensions));
    }
}
