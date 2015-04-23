<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\CssSelector;

use Symfony\Component\CssSelector\Parser\Shortcut\ClassParser;
use Symfony\Component\CssSelector\Parser\Shortcut\ElementParser;
use Symfony\Component\CssSelector\Parser\Shortcut\EmptyStringParser;
use Symfony\Component\CssSelector\Parser\Shortcut\HashParser;
use Symfony\Component\CssSelector\SelectorCache\ArraySelectorCache;
use Symfony\Component\CssSelector\SelectorCache\SelectorCacheInterface;
use Symfony\Component\CssSelector\XPath\Extension\HtmlExtension;
use Symfony\Component\CssSelector\XPath\Translator;

/**
 * CssSelector is the main entry point of the component and can convert CSS
 * selectors to XPath expressions.
 *
 * $xpath = CssSelector::toXpath('h1.foo');
 *
 * This component is a port of the Python cssselect library,
 * which is copyright Ian Bicking, @see https://github.com/SimonSapin/cssselect.
 *
 * Copyright (c) 2007-2012 Ian Bicking and contributors. See AUTHORS
 * for more details.
 *
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are
 * met:
 *
 * 1. Redistributions of source code must retain the above copyright
 * notice, this list of conditions and the following disclaimer.
 *
 * 2. Redistributions in binary form must reproduce the above copyright
 * notice, this list of conditions and the following disclaimer in
 * the documentation and/or other materials provided with the
 * distribution.
 *
 * 3. Neither the name of Ian Bicking nor the names of its contributors may
 * be used to endorse or promote products derived from this software
 * without specific prior written permission.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
 * "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
 * LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR
 * A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL IAN BICKING OR
 * CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL,
 * EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO,
 * PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR
 * PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF
 * LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING
 * NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS
 * SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @api
 */
class CssSelector
{
    private static $html = true;

    /**
     * @var SelectorCacheInterface
     */
    private static $cache;

    /**
     * Translates a CSS expression to its XPath equivalent.
     * Optionally, a prefix can be added to the resulting XPath
     * expression with the $prefix parameter.
     *
     * @param mixed  $cssExpr The CSS expression.
     * @param string $prefix  An optional prefix for the XPath expression.
     *
     * @return string
     *
     * @api
     */
    public static function toXPath($cssExpr, $prefix = 'descendant-or-self::')
    {
        self::$cache = self::$cache ?: new ArraySelectorCache();

        $key = ($prefix ?: '').$cssExpr.(self::$html ? '::html' : '');

        if (null !== $xpath = self::$cache->fetch($key)) {
            return $xpath;
        }

        $translator = new Translator();

        if (self::$html) {
            $translator->registerExtension(new HtmlExtension($translator));
        }

        $translator
            ->registerParserShortcut(new EmptyStringParser())
            ->registerParserShortcut(new ElementParser())
            ->registerParserShortcut(new ClassParser())
            ->registerParserShortcut(new HashParser())
        ;

        $xpath = $translator->cssToXPath($cssExpr, $prefix);

        self::$cache->save($key, $xpath);

        return $xpath;
    }

    /**
     * Enables the HTML extension.
     */
    public static function enableHtmlExtension()
    {
        self::$html = true;
    }

    /**
     * Disables the HTML extension.
     */
    public static function disableHtmlExtension()
    {
        self::$html = false;
    }

    /**
     * Sets a custom selector cache.
     *
     * @param SelectorCacheInterface|null $cache
     */
    public static function setSelectorCache(SelectorCacheInterface $cache = null)
    {
        self::$cache = $cache;
    }
}
