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
final readonly class ParsedStylesheet
{
    /**
     * @param array<int, CssRule>                 $rules
     * @param array<string, array>                $registered
     * @param array<string, array>                $keyframes
     * @param array<string, array<string, mixed>> $counterStyles
     * @param array<string, array>                $functions
     * @param array<string, array>                $positionTries
     */
    public function __construct(
        public array $rules = [],
        public array $registered = [],
        public array $keyframes = [],
        public array $counterStyles = [],
        public array $functions = [],
        public array $positionTries = [],
    ) {
    }
}
