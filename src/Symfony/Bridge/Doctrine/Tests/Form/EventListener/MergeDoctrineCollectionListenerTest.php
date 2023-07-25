<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Doctrine\Tests\Form\EventListener;

use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Doctrine\Form\EventListener\MergeDoctrineCollectionListener;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Form\FormBuilder;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormFactoryInterface;

class MergeDoctrineCollectionListenerTest extends TestCase
{
    private ArrayCollection $collection;
    private EventDispatcher $dispatcher;
    private MockObject&FormFactoryInterface $factory;

    protected function setUp(): void
    {
        $this->collection = new ArrayCollection(['test']);
        $this->dispatcher = new EventDispatcher();
        $this->factory = $this->createMock(FormFactoryInterface::class);
    }

    protected function getBuilder()
    {
        return new FormBuilder('name', null, $this->dispatcher, $this->factory);
    }

    protected function getForm()
    {
        return $this->getBuilder()
            ->setData($this->collection)
            ->addEventSubscriber(new MergeDoctrineCollectionListener())
            ->getForm();
    }

    public function testOnSubmitDoNothing()
    {
        $submittedData = ['test'];
        $event = new FormEvent($this->getForm(), $submittedData);

        $this->dispatcher->dispatch($event, FormEvents::SUBMIT);

        $this->assertTrue($this->collection->contains('test'));
        $this->assertSame(1, $this->collection->count());
    }

    public function testOnSubmitNullClearCollection()
    {
        $submittedData = [];
        $event = new FormEvent($this->getForm(), $submittedData);

        $this->dispatcher->dispatch($event, FormEvents::SUBMIT);

        $this->assertTrue($this->collection->isEmpty());
    }
}
