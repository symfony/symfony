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
 * @author Grégoire Pineau <lyrixx@lyrixx.info>
 */
#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
final class AsEnterListener extends AsEventListener
{
    use BuildEventNameTrait;

    public function __construct(
        ?string $workflow = null,
        ?string $place = null,
        ?string $method = null,
        int $priority = 0,
        ?string $dispatcher = null,
    ) {
        parent::__construct($this->buildEventName('enter', 'place', $workflow, $place), $method, $priority, $dispatcher);
    }
}
