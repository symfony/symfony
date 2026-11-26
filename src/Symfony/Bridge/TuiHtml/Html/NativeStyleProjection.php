<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\TuiHtml\Html;

/** @internal */
final class NativeStyleProjection
{
    /**
     * Avoid applying a shorthand and its unchanged expansion twice to a device style.
     * Longhands won by another declaration remain authoritative over the shorthand.
     *
     * @param array{computed: array<string, string>, cascaded?: array<string, string>, declarations: array<string, array{source: string, expansion: list<string>, priority: array, order: array{int, int}, valid: bool, value: string, source-value: string, initial: bool, implicit: bool, explicit-candidate: array{0: string, ...}|null}>} $record
     *
     * @return array<string, string>
     */
    public static function project(array $record): array
    {
        $values = $record['computed'];
        $declarations = $record['declarations'];
        $partial = ['text-emphasis' => true];
        unset($values['text-emphasis']);
        foreach ($declarations as $property => $declaration) {
            if ($property !== $declaration['source']) {
                continue;
            }
            foreach ($declaration['expansion'] as $expanded) {
                if (isset($declarations[$expanded]) && $declarations[$expanded]['priority'] !== $declaration['priority']) {
                    $partial[$property] = true;
                    unset($values[$property]);
                    break;
                }
            }
        }
        foreach ($declarations as $property => $declaration) {
            $source = $declaration['source'];
            if (str_starts_with($property, '--') && $declaration['valid'] && !\in_array(strtolower(trim($declaration['value'])), ['initial', 'inherit', 'unset', 'revert', 'revert-layer'], true)) {
                $values[$property] = $record['cascaded'][$property] ?? $declaration['value'];
            }
            if (!isset($partial[$source]) && $source !== $property && isset($declarations[$source], $values[$source]) && $declaration['order'] === $declarations[$source]['order'] && $declaration['source-value'] === $declarations[$source]['source-value']) {
                unset($values[$property]);
            } elseif ('line-height' === $property && is_numeric($declaration['value'])) {
                // The terminal font remains one cell high: retain the CSS multiplier for that device font.
                $values[$property] = $declaration['value'];
            } elseif ($declaration['initial']) {
                // Device defaults need an explicit reset even when CSS omits the initial from its sparse map.
                $values[$property] = 'initial';
            }
        }

        foreach ($declarations as $property => $declaration) {
            if (str_starts_with($property, 'border-') && str_ends_with($property, '-color') && $declaration['implicit'] && null !== $candidate = $declaration['explicit-candidate']) {
                $values[$property] = $candidate[0];
            }
        }
        uasort($declarations, static fn (array $left, array $right): int => $left['order'] <=> $right['order']);
        foreach ($declarations as $property => $_) {
            if (\array_key_exists($property, $values)) {
                $value = $values[$property];
                unset($values[$property]);
                $values[$property] = $value;
            }
        }

        return $values;
    }
}
