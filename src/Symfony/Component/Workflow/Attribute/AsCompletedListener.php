<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Workflow\Attribute;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * Defines a listener for the "completed" event of a workflow.
 *
 * @author Grégoire Pineau <lyrixx@lyrixx.info>
 */
#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
final class AsCompletedListener extends AsEventListener
{
    use BuildEventNameTrait;

    /**
     * @param string|null $workflow   The id of the workflow to listen to
     * @param string|null $transition The transition name to which the listener listens to
     * @param string|null $method     The method to run when the listened event is triggered
     * @param int         $priority   The priority of this listener if several are declared for the same transition
     * @param string|null $dispatcher The service id of the event dispatcher to listen to
     */
    public function __construct(
        ?string $workflow = null,
        ?string $transition = null,
        ?string $method = null,
        int $priority = 0,
        ?string $dispatcher = null,
    ) {
        parent::__construct($this->buildEventName('completed', 'transition', $workflow, $transition), $method, $priority, $dispatcher);
    }
}
