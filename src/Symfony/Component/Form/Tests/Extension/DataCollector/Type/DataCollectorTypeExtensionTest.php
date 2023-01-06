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

use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Form\Extension\DataCollector\FormDataCollector;
use Symfony\Component\Form\Extension\DataCollector\FormDataExtractor;
use Symfony\Component\Form\Extension\DataCollector\Type\DataCollectorTypeExtension;
use Symfony\Component\Form\FormBuilder;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormFactory;
use Symfony\Component\Form\FormRegistry;
use Symfony\Component\Form\ResolvedFormTypeFactory;

class DataCollectorTypeExtensionTest extends TestCase
{
    /**
     * @var DataCollectorTypeExtension
     */
    private $extension;

    protected function setUp(): void
    {
        $this->extension = new DataCollectorTypeExtension(new FormDataCollector(new FormDataExtractor()));
    }

    public function testGetExtendedType()
    {
        $this->assertEquals(['Symfony\Component\Form\Extension\Core\Type\FormType'], $this->extension::getExtendedTypes());
    }

    public function testBuildForm()
    {
        $eventDispatcher = new EventDispatcher();
        $this->assertFalse($eventDispatcher->hasListeners(FormEvents::PRE_SET_DATA));
        $this->assertFalse($eventDispatcher->hasListeners(FormEvents::POST_SET_DATA));
        $this->assertFalse($eventDispatcher->hasListeners(FormEvents::PRE_SUBMIT));
        $this->assertFalse($eventDispatcher->hasListeners(FormEvents::SUBMIT));
        $this->assertFalse($eventDispatcher->hasListeners(FormEvents::POST_SUBMIT));

        $this->extension->buildForm(new FormBuilder(null, null, $eventDispatcher, new FormFactory(new FormRegistry([], new ResolvedFormTypeFactory()))), []);

        $this->assertFalse($eventDispatcher->hasListeners(FormEvents::PRE_SET_DATA));
        $this->assertTrue($eventDispatcher->hasListeners(FormEvents::POST_SET_DATA));
        $this->assertFalse($eventDispatcher->hasListeners(FormEvents::PRE_SUBMIT));
        $this->assertFalse($eventDispatcher->hasListeners(FormEvents::SUBMIT));
        $this->assertTrue($eventDispatcher->hasListeners(FormEvents::POST_SUBMIT));
    }
}
