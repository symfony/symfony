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

/**
 * @author Grégoire Pineau <lyrixx@lyrixx.info>
 *
 * @internal
 */
trait BuildEventNameTrait
{
    private static function buildEventName(string $keyword, string $argument, ?string $workflow = null, ?string $node = null): string
    {
        if (null === $workflow) {
            if (null === $node) {
                return \sprintf('workflow.%s', $keyword);
            }

            // The name of the workflow is resolved when the container is
            // compiled, from the AsWorkflow attribute of the class of the listener
            $workflow = AsWorkflow::NAME_PLACEHOLDER;
        }

        if (null === $node) {
            return \sprintf('workflow.%s.%s', $workflow, $keyword);
        }

        return \sprintf('workflow.%s.%s.%s', $workflow, $keyword, $node);
    }
}
