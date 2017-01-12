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
use Symfony\Component\Workflow\Definition;
use Symfony\Component\Workflow\Marking;
use Symfony\Component\Workflow\Registry;
use Symfony\Component\Workflow\Workflow;

class WorkflowExtensionTest extends \PHPUnit_Framework_TestCase
{
    protected function setUp()
    {
        if (!class_exists(Workflow::class)) {
            $this->markTestSkipped('The Workflow component is needed to run tests for this extension.');
        }
    }

    public function testHasMarkedPlace()
    {
        $definition = new Definition(['ordered', 'waiting_for_payment', 'processed'], []);
        $workflow = new Workflow($definition);

        $registry = new Registry();
        $registry->add($workflow, \stdClass::class);

        $extension = new WorkflowExtension($registry);

        $subject = new \stdClass();
        $subject->marking = array('ordered' => 1, 'waiting_for_payment' => 1);

        $this->assertTrue($extension->hasMarkedPlace($subject, 'ordered'));
        $this->assertTrue($extension->hasMarkedPlace($subject, 'waiting_for_payment'));
        $this->assertFalse($extension->hasMarkedPlace($subject, 'processed'));
    }
}
