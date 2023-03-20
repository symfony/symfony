<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\JsonEncoder;

/**
 * @author Mathias Arlaud <mathias.arlaud@gmail.com>
 *
 * @experimental
 *
 * @internal
 */
trait VariableNameScoperTrait
{
    /**
     * @param array{variable_counters?: array<string, int>}&array<string, mixed> $context
     */
    private function scopeVariableName(string $variableName, array &$context): string
    {
        if (!isset($context['variable_counters'][$variableName])) {
            $context['variable_counters'][$variableName] = 0;
        }

        $name = sprintf('%s_%d', $variableName, $context['variable_counters'][$variableName]);

        ++$context['variable_counters'][$variableName];

        return $name;
    }
}
