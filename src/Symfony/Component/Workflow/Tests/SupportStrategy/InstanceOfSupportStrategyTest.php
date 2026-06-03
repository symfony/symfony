<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Workflow\Tests\SupportStrategy;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Workflow\SupportStrategy\InstanceOfSupportStrategy;
use Symfony\Component\Workflow\WorkflowInterface;

class InstanceOfSupportStrategyTest extends TestCase
{
    public function testSupportsIfClassInstance()
    {
        $strategy = new InstanceOfSupportStrategy(Subject1::class);

        $this->assertTrue($strategy->supports($this->createStub(WorkflowInterface::class), new Subject1()));
    }

    public function testSupportsIfNotClassInstance()
    {
        $strategy = new InstanceOfSupportStrategy(Subject2::class);

        $this->assertFalse($strategy->supports($this->createStub(WorkflowInterface::class), new Subject1()));
    }
}

class Subject1
{
}
class Subject2
{
}
