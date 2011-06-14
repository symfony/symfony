<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Component\Form\Extension\Csrf\EventListener;

use Symfony\Component\Form\Event\DataEvent;
use Symfony\Component\Form\Extension\Csrf\EventListener\EnsureCsrfFieldListener;

class EnsureCsrfFieldListenerTest extends \PHPUnit_Framework_TestCase
{
    private $form;
    private $formFactory;
    private $field;
    private $event;

    protected function setUp()
    {
        $this->formFactory = $this->getMock('Symfony\\Component\\Form\\FormFactoryInterface');
        $this->form = $this->getMock('Symfony\\Tests\\Component\\Form\\FormInterface');
        $this->field = $this->getMock('Symfony\\Tests\\Component\\Form\\FormInterface');
        $this->event = new DataEvent($this->form, array());
    }

    protected function tearDown()
    {
        $this->form = null;
        $this->formFactory = null;
        $this->field = null;
        $this->event = null;
    }

    public function testAddField()
    {
        $this->formFactory->expects($this->once())
            ->method('createNamed')
            ->with('csrf', '_token', null, array())
            ->will($this->returnValue($this->field));
        $this->form->expects($this->once())
            ->method('add')
            ->with($this->isInstanceOf('Symfony\\Tests\\Component\\Form\\FormInterface'));

        $listener = new EnsureCsrfFieldListener($this->formFactory, '_token');
        $listener->ensureCsrfField($this->event);
    }

    public function testIntention()
    {
        $this->formFactory->expects($this->once())
            ->method('createNamed')
            ->with('csrf', '_token', null, array('intention' => 'something'))
            ->will($this->returnValue($this->field));
        $this->form->expects($this->once())
            ->method('add')
            ->with($this->isInstanceOf('Symfony\\Tests\\Component\\Form\\FormInterface'));

        $listener = new EnsureCsrfFieldListener($this->formFactory, '_token', 'something');
        $listener->ensureCsrfField($this->event);
    }

    public function testProvider()
    {
        $provider = $this->getMock('Symfony\\Component\\Form\\Extension\\Csrf\\CsrfProvider\\CsrfProviderInterface');

        $this->formFactory->expects($this->once())
            ->method('createNamed')
            ->with('csrf', '_token', null, array('csrf_provider' => $provider))
            ->will($this->returnValue($this->field));
        $this->form->expects($this->once())
            ->method('add')
            ->with($this->isInstanceOf('Symfony\\Tests\\Component\\Form\\FormInterface'));

        $listener = new EnsureCsrfFieldListener($this->formFactory, '_token', null, $provider);
        $listener->ensureCsrfField($this->event);
    }
}
