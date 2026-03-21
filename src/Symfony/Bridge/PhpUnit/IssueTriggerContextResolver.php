<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\PhpUnit;

use PHPUnit\Runner\IssueTriggerResolver\Resolution;
use PHPUnit\Runner\IssueTriggerResolver\Resolver;
use Symfony\Contracts\Deprecation\IssueTriggerContextProvider;

/**
 * PHPUnit IssueTriggerResolver that delegates to any {@see IssueTriggerContextProvider} found in
 * the call stack.
 *
 * Scans the trace from the innermost frame outward for the nearest object implementing
 * {@see IssueTriggerContextProvider}. Because trigger_error() executes synchronously, only the
 * innermost provider can be the direct cause of the current error. Its answer is authoritative:
 * if it returns a non-null context, that context is used to build the Resolution; if it returns
 * null (meaning it is a bystander, not the active dispatcher), this resolver abstains immediately
 * rather than searching further.
 *
 * @author Matthias Pigulla <mp@webfactory.de>
 */
final class IssueTriggerContextResolver implements Resolver
{
    public function resolve(array $trace, string $message): ?Resolution
    {
        foreach ($trace as $frame) {
            if (!isset($frame['object']) || !$frame['object'] instanceof IssueTriggerContextProvider) {
                continue;
            }

            // Nearest provider found — its answer is final. If it returns null it is not the
            // active dispatcher of the current error; searching further would be unreliable.
            $context = $frame['object']->getIssueTriggerContext();
            if (null === $context) {
                return null;
            }

            return new Resolution($context->getCalleeFile(), $context->getCallerFile());
        }

        return null;
    }
}
