<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Twig\Tests\Extension;

use Symfony\Bridge\Twig\Extension\WorkflowExtension;
use Symfony\Component\Workflow\Marking;
use Symfony\Component\Workflow\Registry;
use Symfony\Component\Workflow\Workflow;

class WorkflowExtensionTest extends \PHPUnit_Framework_TestCase
{
    protected function setUp()
    {
        parent::setUp();

        if (!class_exists('Symfony\Component\Workflow\Workflow')) {
            $this->markTestSkipped('The Workflow component is needed to run tests for this extension.');
        }
    }

    public function testHasPlace()
    {
        $subject = new \stdClass();

        $marking = new Marking(array('ordered' => true, 'waiting_for_payment' => true));

        $workflow = $this->getMock(Workflow::class, array(), array(), '', false);
        $workflow->expects($this->exactly(3))
             ->method('getMarking')
             ->with($subject)
             ->will($this->returnValue($marking));

        $registry = $this->getMock(Registry::class);
        $registry->expects($this->exactly(3))
             ->method('get')
             ->with($subject)
             ->will($this->returnValue($workflow));

        $extension = new WorkflowExtension($registry);

        $this->assertTrue($extension->hasPlace($subject, 'ordered'));
        $this->assertTrue($extension->hasPlace($subject, 'waiting_for_payment'));
        $this->assertFalse($extension->hasPlace($subject, 'processed'));
    }
}
