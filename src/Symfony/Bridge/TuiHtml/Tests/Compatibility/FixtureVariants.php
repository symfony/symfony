<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\TuiHtml\Tests\Compatibility;

final class FixtureVariants
{
    public static function fromHtml(string $html): iterable
    {
        yield ['regular', []];
        yield ['condensed', ['density' => 'condensed']];
        preg_match_all('/<!--\s*variant:\s*(\S+)(.*?)-->/', $html, $variants, \PREG_SET_ORDER);
        foreach ($variants as [, $name, $raw]) {
            preg_match_all('/([a-z-]+)(?:=(\S+))?/', $raw, $pairs, \PREG_SET_ORDER);
            yield [$name, array_column(array_map(static fn (array $pair): array => [$pair[1], $pair[2] ?? '1'], $pairs), 1, 0)];
        }
    }
}
