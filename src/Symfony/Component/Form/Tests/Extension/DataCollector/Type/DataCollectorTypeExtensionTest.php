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


use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Form\Extension\DataCollector\Type\DataCollectorTypeExtension;

class DataCollectorTypeExtensionTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var DataCollectorTypeExtension
     */
    private $extension;

    /**
     * @var EventSubscriberInterface
     */
    private $eventSubscriber;

    public function setUp()
    {
        $this->eventSubscriber = $this->getMock('Symfony\Component\EventDispatcher\EventSubscriberInterface');
        $this->extension = new DataCollectorTypeExtension($this->eventSubscriber);
    }

    public function testGetExtendedType()
    {
        $this->assertEquals('form', $this->extension->getExtendedType());
    }

    public function testBuildForm()
    {
        $builder = $this->getMock('Symfony\Component\Form\Test\FormBuilderInterface');
        $builder->expects($this->atLeastOnce())->method('addEventSubscriber')->with($this->eventSubscriber);

        $this->extension->buildForm($builder, array());
    }
}
 