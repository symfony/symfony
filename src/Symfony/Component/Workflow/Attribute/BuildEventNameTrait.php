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

use Symfony\Component\Workflow\Exception\LogicException;

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
            if (null !== $node) {
                throw new LogicException(sprintf('The "%s" argument of "%s" cannot be used without a "workflow" argument.', $argument, self::class));
            }

            return sprintf('workflow.%s', $keyword);
        }

        if (null === $node) {
            return sprintf('workflow.%s.%s', $workflow, $keyword);
        }

        return sprintf('workflow.%s.%s.%s', $workflow, $keyword, $node);
    }
}
