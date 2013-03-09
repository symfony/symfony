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

use Symfony\Component\CssSelector\Exception;
use Symfony\Component\CssSelector\XPath\Extension\HtmlExtension;
use Symfony\Component\CssSelector\XPath\Translator;

/**
 * CssSelector is the main entry point of the component and can convert CSS
 * selectors to XPath expressions.
 *
 * $xpath = CssSelector::toXpath('h1.foo');
 *
 * This component is a port of the Python lxml library,
 * which is copyright Infrae and distributed under the BSD license.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @api
 */
class CssSelector
{
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
     * @throws Exception\ParseException When got null for xpath expression
     *
     * @api
     */
    public static function toXPath($cssExpr, $prefix = 'descendant-or-self::')
    {
        $translator = new Translator();
        // todo: add a way to switch on/off HTML extension without BC break
        $translator->registerExtension(new HtmlExtension($translator));

        try {
            return $translator->cssToXPath($cssExpr, $prefix);
        } catch (Exception\SyntaxErrorException $e) {
            throw new Exception\ParseException('Syntax error: '.$e->getMessage(), 0, $e);
        } catch (Exception\ExpressionErrorException $e) {
            throw new Exception\ParseException('Expression error: '.$e->getMessage(), 0, $e);
        } catch (Exception\InternalErrorException $e) {
            throw new Exception\ParseException('Internal error: '.$e->getMessage(), 0, $e);
        }
    }
}
