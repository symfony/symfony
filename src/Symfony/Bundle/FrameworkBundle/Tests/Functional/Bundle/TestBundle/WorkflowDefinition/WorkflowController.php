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

use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Workflow\WorkflowInterface;

class WorkflowController
{
    public function __construct(
        #[Target('order.state_machine')]
        private WorkflowInterface $orderWorkflow,
        #[Target('article.workflow')]
        private WorkflowInterface $articleWorkflow,
    ) {
    }

    public function __invoke(): Response
    {
        $order = new Order();
        $orderBefore = array_key_first($this->orderWorkflow->getMarking($order)->getPlaces());
        $canReview = $this->orderWorkflow->can($order, 'review');
        $this->orderWorkflow->apply($order, 'review');
        $orderAfter = array_key_first($this->orderWorkflow->getMarking($order)->getPlaces());

        $article = new Article();
        $articleBefore = array_key_first($this->articleWorkflow->getMarking($article)->getPlaces());
        $canPublish = $this->articleWorkflow->can($article, 'publish');
        $this->articleWorkflow->apply($article, 'publish');
        $articleAfter = array_key_first($this->articleWorkflow->getMarking($article)->getPlaces());

        return new Response(\sprintf(
            'order:%s|%s|%s;article:%s|%s|%s',
            $orderBefore,
            $canReview ? 'yes' : 'no',
            $orderAfter,
            $articleBefore,
            $canPublish ? 'yes' : 'no',
            $articleAfter,
        ));
    }
}
