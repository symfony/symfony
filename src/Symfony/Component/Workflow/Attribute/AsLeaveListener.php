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
 * Defines a listener for the "leave" event of a workflow.
 *
 * @author Grégoire Pineau <lyrixx@lyrixx.info>
 */
#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
final class AsLeaveListener extends AsEventListener
{
    use BuildEventNameTrait;

    /**
     * @param string|null $workflow   The id of the workflow to listen to
     * @param string|null $place      The place name to which the listener listens to
     * @param string|null $method     The method to run when the listened event is triggered
     * @param int         $priority   The priority of this listener if several are declared for the same place
     * @param string|null $dispatcher The service id of the event dispatcher to listen to
     */
    public function __construct(
        ?string $workflow = null,
        ?string $place = null,
        ?string $method = null,
        int $priority = 0,
        ?string $dispatcher = null,
    ) {
        parent::__construct($this->buildEventName('leave', 'place', $workflow, $place), $method, $priority, $dispatcher);
    }
}
