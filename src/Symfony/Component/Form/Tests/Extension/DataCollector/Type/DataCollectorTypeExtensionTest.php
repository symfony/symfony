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

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Extension\DataCollector\EventListener\DataCollectorListener;
use Symfony\Component\Form\Extension\DataCollector\FormDataCollectorInterface;
use Symfony\Component\Form\Extension\DataCollector\Type\DataCollectorTypeExtension;
use Symfony\Component\Form\Test\FormBuilderInterface;

class DataCollectorTypeExtensionTest extends TestCase
{
    /**
     * @var DataCollectorTypeExtension
     */
    private $extension;

    /**
     * @var MockObject&FormDataCollectorInterface
     */
    private $dataCollector;

    protected function setUp(): void
    {
        $this->dataCollector = $this->createMock(FormDataCollectorInterface::class);
        $this->extension = new DataCollectorTypeExtension($this->dataCollector);
    }

    public function testGetExtendedType()
    {
        $this->assertEquals(['Symfony\Component\Form\Extension\Core\Type\FormType'], $this->extension::getExtendedTypes());
    }

    public function testBuildForm()
    {
        $builder = $this->createMock(FormBuilderInterface::class);
        $builder->expects($this->atLeastOnce())
            ->method('addEventSubscriber')
            ->with($this->isInstanceOf(DataCollectorListener::class));

        $this->extension->buildForm($builder, []);
    }
}
