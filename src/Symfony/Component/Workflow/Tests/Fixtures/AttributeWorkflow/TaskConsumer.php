<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Workflow\Tests\Fixtures\AttributeWorkflow;

use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Workflow\WorkflowInterface;

final class TaskConsumer
{
    public function __construct(
        public readonly TaskWorkflow $taskWorkflow,
        #[Target('task')]
        public readonly WorkflowInterface $workflow,
    ) {
    }
}
