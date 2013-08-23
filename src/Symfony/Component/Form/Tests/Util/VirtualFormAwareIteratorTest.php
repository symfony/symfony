<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Tests\Util;

use Symfony\Component\Form\Util\VirtualFormAwareIterator;

/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class VirtualFormAwareIteratorTest extends \PHPUnit_Framework_TestCase
{
    public function testSupportDynamicModification()
    {
        $form = $this->getMockForm('form');
        $formToBeAdded = $this->getMockForm('added');
        $formToBeRemoved = $this->getMockForm('removed');

        $forms = array('form' => $form, 'removed' => $formToBeRemoved);
        $iterator = new VirtualFormAwareIterator($forms);

        $iterator->rewind();
        $this->assertTrue($iterator->valid());
        $this->assertSame('form', $iterator->key());
        $this->assertSame($form, $iterator->current());

        // dynamic modification
        unset($forms['removed']);
        $forms['added'] = $formToBeAdded;

        // continue iteration
        $iterator->next();
        $this->assertTrue($iterator->valid());
        $this->assertSame('added', $iterator->key());
        $this->assertSame($formToBeAdded, $iterator->current());

        // end of array
        $iterator->next();
        $this->assertFalse($iterator->valid());
    }

    public function testSupportDynamicModificationInRecursiveCall()
    {
        $virtualForm = $this->getMockForm('virtual');
        $form = $this->getMockForm('form');
        $formToBeAdded = $this->getMockForm('added');
        $formToBeRemoved = $this->getMockForm('removed');

        $virtualForm->getConfig()->expects($this->any())
            ->method('getVirtual')
            ->will($this->returnValue(true));

        $virtualForm->add($form);
        $virtualForm->add($formToBeRemoved);

        $forms = array('virtual' => $virtualForm);
        $iterator = new VirtualFormAwareIterator($forms);

        $iterator->rewind();
        $this->assertTrue($iterator->valid());
        $this->assertSame('virtual', $iterator->key());
        $this->assertSame($virtualForm, $iterator->current());
        $this->assertTrue($iterator->hasChildren());

        // enter nested iterator
        $nestedIterator = $iterator->getChildren();
        $this->assertSame('form', $nestedIterator->key());
        $this->assertSame($form, $nestedIterator->current());
        $this->assertFalse($nestedIterator->hasChildren());

        // dynamic modification
        $virtualForm->remove('removed');
        $virtualForm->add($formToBeAdded);

        // continue iteration - nested iterator discovers change in the form
        $nestedIterator->next();
        $this->assertTrue($nestedIterator->valid());
        $this->assertSame('added', $nestedIterator->key());
        $this->assertSame($formToBeAdded, $nestedIterator->current());

        // end of array
        $nestedIterator->next();
        $this->assertFalse($nestedIterator->valid());
    }

    /**
     * @param  string $name
     *
     * @return \PHPUnit_Framework_MockObject_MockObject
     */
    protected function getMockForm($name = 'name')
    {
        $config = $this->getMock('Symfony\Component\Form\FormConfigInterface');

        $config->expects($this->any())
            ->method('getName')
            ->will($this->returnValue($name));
        $config->expects($this->any())
            ->method('getCompound')
            ->will($this->returnValue(true));
        $config->expects($this->any())
            ->method('getDataMapper')
            ->will($this->returnValue($this->getMock('Symfony\Component\Form\DataMapperInterface')));
        $config->expects($this->any())
            ->method('getEventDispatcher')
            ->will($this->returnValue($this->getMock('Symfony\Component\EventDispatcher\EventDispatcher')));

        return $this->getMockBuilder('Symfony\Component\Form\Form')
            ->setConstructorArgs(array($config))
            ->disableArgumentCloning()
            ->setMethods(array('getViewData'))
            ->getMock();
    }
}
