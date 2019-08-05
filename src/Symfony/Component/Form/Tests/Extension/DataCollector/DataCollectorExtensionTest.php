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

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Extension\DataCollector\DataCollectorExtension;

class DataCollectorExtensionTest extends TestCase
{
    /**
     * @var DataCollectorExtension
     */
    private $extension;

    /**
     * @var MockObject
     */
    private $dataCollector;

    protected function setUp()
    {
        $this->dataCollector = $this->getMockBuilder('Symfony\Component\Form\Extension\DataCollector\FormDataCollectorInterface')->getMock();
        $this->extension = new DataCollectorExtension($this->dataCollector);
    }

    public function testLoadTypeExtensions()
    {
        $typeExtensions = $this->extension->getTypeExtensions('Symfony\Component\Form\Extension\Core\Type\FormType');

        $this->assertIsArray($typeExtensions);
        $this->assertCount(1, $typeExtensions);
        $this->assertInstanceOf('Symfony\Component\Form\Extension\DataCollector\Type\DataCollectorTypeExtension', array_shift($typeExtensions));
    }
}
