<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HtmlRenderer\Html;

/** @internal */
final class RubySegments
{
    /** @return list<array{list<list<int>>, list<int>}> */
    public static function pair(array $roles, array $blank = []): array
    {
        $segments = [];
        $bases = [];
        $run = [];
        $annotations = [];
        foreach ($roles as $i => $role) {
            if (($blank[$i] ?? false) || 'none' === $role) {
                continue;
            }
            if (\in_array($role, ['ruby-text', 'ruby-text-container'], true)) {
                if ([] !== $run) {
                    $bases[] = $run;
                    $run = [];
                }
                $annotations[] = $i;
                continue;
            }
            if ([] !== $annotations) {
                $segments[] = [$bases, $annotations];
                $bases = [];
                $annotations = [];
            }
            if ('ruby-base' === $role) {
                if ([] !== $run) {
                    $bases[] = $run;
                    $run = [];
                }
                $bases[] = [$i];
                continue;
            }
            $run[] = $i;
        }
        if ([] !== $run) {
            $bases[] = $run;
        }
        $segments[] = [$bases, $annotations];

        return $segments;
    }
}
