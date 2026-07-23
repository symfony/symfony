<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Tui\Widget\Markdown;

use Tempest\Highlight\TerminalTheme;
use Tempest\Highlight\Themes\EscapesTerminalTheme;
use Tempest\Highlight\Tokens\TokenType;
use Tempest\Highlight\Tokens\TokenTypeEnum;

/**
 * Dark terminal theme for syntax highlighting.
 *
 * @experimental
 *
 * @internal
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
final class DarkTerminalTheme implements TerminalTheme
{
    use EscapesTerminalTheme;

    public function before(TokenType $tokenType): string
    {
        $rgb = match ($tokenType) {
            TokenTypeEnum::KEYWORD => [255, 122, 178],   // #ff7ab2
            TokenTypeEnum::TYPE => [172, 242, 228],      // #acf2e4
            TokenTypeEnum::PROPERTY => [120, 199, 255],  // #78c7ff (variable)
            TokenTypeEnum::VARIABLE => [120, 199, 255],  // #78c7ff (variable)
            TokenTypeEnum::GENERIC => [78, 176, 255],    // #4eb0ff (function)
            TokenTypeEnum::COMMENT => [106, 106, 122],   // #6a6a7a
            TokenTypeEnum::VALUE => [217, 201, 124],     // #d9c97c (string/number)
            TokenTypeEnum::ATTRIBUTE => [178, 129, 235], // #b281eb
            TokenTypeEnum::OPERATOR => [178, 129, 235],  // #b281eb
            default => null,
        };

        if (null === $rgb) {
            return '';
        }

        // Use 24-bit RGB escape sequence
        return \sprintf("\x1b[38;2;%d;%d;%dm", $rgb[0], $rgb[1], $rgb[2]);
    }

    public function after(TokenType $tokenType): string
    {
        return "\x1b[39m"; // Reset foreground only
    }
}
