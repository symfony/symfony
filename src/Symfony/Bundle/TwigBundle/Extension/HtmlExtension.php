<?php

namespace Symfony\Bundle\TwigBundle\Extension;

use Symfony\Bundle\FrameworkBundle\Templating\HtmlGeneratorInterface;
use Symfony\Bundle\TwigBundle\TokenParser\TagTokenParser;
use Symfony\Bundle\TwigBundle\TokenParser\ContentTagTokenParser;
use Symfony\Bundle\TwigBundle\TokenParser\ChoiceTagTokenParser;

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
 * @author Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class HtmlExtension extends \Twig_Extension
{
    protected $generator;

    public function __construct(HtmlGeneratorInterface $generator)
    {
        $this->generator = $generator;
    }

    public function getGenerator()
    {
        return $this->generator;
    }

    /**
     * Returns the token parser instance to add to the existing list.
     *
     * @return array An array of Twig_TokenParser instances
     */
    public function getTokenParsers()
    {
        return array(
            // {% tag "input" with attributes %}
            new TagTokenParser(),

            // {% contenttag "textarea" with attributes %}content{% endcontenttag %}
            new ContentTagTokenParser(),
        );
    }

    /**
     * Returns the name of the extension.
     *
     * @return string The extension name
     */
    public function getName()
    {
        return 'html';
    }
}
