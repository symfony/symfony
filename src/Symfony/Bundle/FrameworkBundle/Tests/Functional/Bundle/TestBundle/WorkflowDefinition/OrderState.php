<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests\Functional\Bundle\TestBundle\WorkflowDefinition;

use Symfony\Component\Workflow\Attribute\AsWorkflowDefinition;
use Symfony\Component\Workflow\Attribute\Transition;

#[AsWorkflowDefinition(name: 'order', supports: [Order::class], initialMarking: self::Draft, markingStoreProperty: 'state')]
enum OrderState: string
{
    #[Transition(name: 'review', to: self::Reviewed)]
    case Draft = 'draft';

    case Reviewed = 'reviewed';
}
