<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HtmlSanitizer\Parser;

use Masterminds\HTML5;

/**
 * @deprecated since Symfony 7.4, use `NativeParser` instead
 *
 * @author Titouan Galopin <galopintitouan@gmail.com>
 */
final class MastermindsParser implements ParserInterface
{
    public function __construct(private array $defaultOptions = [])
    {
        if (\PHP_VERSION_ID >= 80400) {
            trigger_deprecation('symfony/html-sanitizer', '7.4', '"%s" is deprecated since Symfony 7.4 and will be removed in 8.0. Use the "NativeParser" instead.', self::class);
        }
    }

    public function parse(string $html, string $context = 'body'): ?\DOMNode
    {
        return (new HTML5($this->defaultOptions))->loadHTMLFragment($html);
    }
}
