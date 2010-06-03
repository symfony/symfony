<?php

namespace Symfony\Framework\TwigBundle\Extension;

use Symfony\Components\Templating\Engine;
use Symfony\Framework\TwigBundle\TokenParser\StylesheetTokenParser;
use Symfony\Framework\TwigBundle\TokenParser\StylesheetsTokenParser;
use Symfony\Framework\TwigBundle\TokenParser\RouteTokenParser;
use Symfony\Framework\TwigBundle\TokenParser\RenderTokenParser;

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 *
 * @package    Symfony
 * @subpackage Framework_TwigBundle
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class Helpers extends \Twig_Extension
{
    /**
     * Returns the token parser instance to add to the existing list.
     *
     * @return array An array of Twig_TokenParser instances
     */
    public function getTokenParsers()
    {
        return array(
            new JavascriptTokenParser(),
            new JavascriptsTokenParser(),
            new StylesheetTokenParser(),
            new StylesheetsTokenParser(),
            new RouteTokenParser(),
            new RenderTokenParser(),
        );
    }

    /**
     * Returns the name of the extension.
     *
     * @return string The extension name
     */
    public function getName()
    {
        return 'symfony.helpers';
    }
}
