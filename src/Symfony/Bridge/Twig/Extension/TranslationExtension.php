<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Twig\Extension;

use Symfony\Bridge\Twig\TokenParser\TransTokenParser;
use Symfony\Bridge\Twig\TokenParser\TransChoiceTokenParser;
use Symfony\Bridge\Twig\TokenParser\TransDefaultDomainTokenParser;
use Symfony\Component\Translation\TranslatorInterface;
use Symfony\Bridge\Twig\NodeVisitor\TranslationNodeVisitor;
use Symfony\Bridge\Twig\NodeVisitor\TranslationDefaultDomainNodeVisitor;

/**
 * Provides integration of the Translation component with Twig.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @since v2.0.0
 */
class TranslationExtension extends \Twig_Extension
{
    private $translator;
    private $translationNodeVisitor;

    /**
     * @since v2.3.0
     */
    public function __construct(TranslatorInterface $translator, \Twig_NodeVisitorInterface $translationNodeVisitor = null)
    {
        if (!$translationNodeVisitor) {
            $translationNodeVisitor = new TranslationNodeVisitor();
        }

        $this->translator = $translator;
        $this->translationNodeVisitor = $translationNodeVisitor;
    }

    /**
     * @since v2.0.0
     */
    public function getTranslator()
    {
        return $this->translator;
    }

    /**
     * {@inheritdoc}
     *
     * @since v2.0.0
     */
    public function getFilters()
    {
        return array(
            new \Twig_SimpleFilter('trans', array($this, 'trans')),
            new \Twig_SimpleFilter('transchoice', array($this, 'transchoice')),
        );
    }

    /**
     * Returns the token parser instance to add to the existing list.
     *
     * @return array An array of Twig_TokenParser instances
     *
     * @since v2.0.0
     */
    public function getTokenParsers()
    {
        return array(
            // {% trans %}Symfony is great!{% endtrans %}
            new TransTokenParser(),

            // {% transchoice count %}
            //     {0} There is no apples|{1} There is one apple|]1,Inf] There is {{ count }} apples
            // {% endtranschoice %}
            new TransChoiceTokenParser(),

            // {% trans_default_domain "foobar" %}
            new TransDefaultDomainTokenParser(),
        );
    }

    /**
     * {@inheritdoc}
     *
     * @since v2.1.0
     */
    public function getNodeVisitors()
    {
        return array($this->translationNodeVisitor, new TranslationDefaultDomainNodeVisitor());
    }

    /**
     * @since v2.1.0
     */
    public function getTranslationNodeVisitor()
    {
        return $this->translationNodeVisitor;
    }

    /**
     * @since v2.1.0
     */
    public function trans($message, array $arguments = array(), $domain = null, $locale = null)
    {
        if (null === $domain) {
            $domain = 'messages';
        }

        return $this->translator->trans($message, $arguments, $domain, $locale);
    }

    /**
     * @since v2.1.0
     */
    public function transchoice($message, $count, array $arguments = array(), $domain = null, $locale = null)
    {
        if (null === $domain) {
            $domain = 'messages';
        }

        return $this->translator->transChoice($message, $count, array_merge(array('%count%' => $count), $arguments), $domain, $locale);
    }

    /**
     * Returns the name of the extension.
     *
     * @return string The extension name
     *
     * @since v2.0.0
     */
    public function getName()
    {
        return 'translator';
    }
}
