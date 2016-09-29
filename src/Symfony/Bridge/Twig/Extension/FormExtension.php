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

use Symfony\Bridge\Twig\TokenParser\FormThemeTokenParser;
use Symfony\Bridge\Twig\Form\TwigRendererInterface;

/**
 * FormExtension extends Twig with form capabilities.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class FormExtension extends \Twig_Extension implements \Twig_Extension_InitRuntimeInterface
{
    /**
     * This property is public so that it can be accessed directly from compiled
     * templates without having to call a getter, which slightly decreases performance.
     *
     * @var TwigRendererInterface
     */
    public $renderer;

    public function __construct(TwigRendererInterface $renderer)
    {
        $this->renderer = $renderer;
    }

    /**
     * {@inheritdoc}
     */
    public function initRuntime(\Twig_Environment $environment)
    {
        $this->renderer->setEnvironment($environment);
    }

    /**
     * {@inheritdoc}
     */
    public function getTokenParsers()
    {
        return array(
            // {% form_theme form "SomeBundle::widgets.twig" %}
            new FormThemeTokenParser(),
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getFunctions()
    {
        return array(
            new \Twig_SimpleFunction('form_widget', null, array('node_class' => 'Symfony\Bridge\Twig\Node\SearchAndRenderBlockNode', 'is_safe' => array('html'))),
            new \Twig_SimpleFunction('form_errors', null, array('node_class' => 'Symfony\Bridge\Twig\Node\SearchAndRenderBlockNode', 'is_safe' => array('html'))),
            new \Twig_SimpleFunction('form_label', null, array('node_class' => 'Symfony\Bridge\Twig\Node\SearchAndRenderBlockNode', 'is_safe' => array('html'))),
            new \Twig_SimpleFunction('form_row', null, array('node_class' => 'Symfony\Bridge\Twig\Node\SearchAndRenderBlockNode', 'is_safe' => array('html'))),
            new \Twig_SimpleFunction('form_rest', null, array('node_class' => 'Symfony\Bridge\Twig\Node\SearchAndRenderBlockNode', 'is_safe' => array('html'))),
            new \Twig_SimpleFunction('form', null, array('node_class' => 'Symfony\Bridge\Twig\Node\RenderBlockNode', 'is_safe' => array('html'))),
            new \Twig_SimpleFunction('form_start', null, array('node_class' => 'Symfony\Bridge\Twig\Node\RenderBlockNode', 'is_safe' => array('html'))),
            new \Twig_SimpleFunction('form_end', null, array('node_class' => 'Symfony\Bridge\Twig\Node\RenderBlockNode', 'is_safe' => array('html'))),
            new \Twig_SimpleFunction('csrf_token', array('Symfony\Bridge\Twig\Form\TwigRenderer', 'renderCsrfToken')),
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getFilters()
    {
        return array(
            new \Twig_SimpleFilter('humanize', array('Symfony\Bridge\Twig\Form\TwigRenderer', 'humanize')),
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getTests()
    {
        return array(
            new \Twig_SimpleTest('selectedchoice', array('Symfony\Bridge\Twig\Form\TwigRenderer', 'isSelectedChoice')),
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'form';
    }
}
