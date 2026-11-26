<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HtmlRenderer\Css;

/** @internal */
final class ParsedStylesheet
{
    /**
     * @param array<int, CssRule>                                                                                                                                                                                                                                                                                                                       $rules
     * @param array<string, array>                                                                                                                                                                                                                                                                                                                      $registered
     * @param array<string, array>                                                                                                                                                                                                                                                                                                                      $keyframes
     * @param array<string, array{system: string, first?: int, symbols?: list<string>, additive?: list<array{int, string}>, negative?: array{string, string}, prefix?: string, suffix?: string, range?: list<array{int|null, int|null}>|null, pad?: array{int, string}|null, fallback?: string, extends?: string, units?: list<string>, formal?: bool}> $counterStyles
     * @param array<string, array>                                                                                                                                                                                                                                                                                                                      $functions
     * @param array<string, array>                                                                                                                                                                                                                                                                                                                      $positionTries
     */
    public function __construct(
        public readonly array $rules = [],
        public readonly array $registered = [],
        public readonly array $keyframes = [],
        public readonly array $counterStyles = [],
        public readonly array $functions = [],
        public readonly array $positionTries = [],
    ) {
    }
}
