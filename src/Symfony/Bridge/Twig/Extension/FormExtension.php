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

use Symfony\Bridge\Twig\Form\TwigRendererInterface;
use Symfony\Bridge\Twig\TokenParser\FormThemeTokenParser;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Form\ChoiceList\View\ChoiceView;
use Symfony\Component\Form\FormView;
use Twig\Environment;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;
use Twig\TwigTest;

/**
 * FormExtension extends Twig with form capabilities.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class FormExtension extends AbstractExtension implements InitRuntimeInterface
{
    /**
     * @deprecated since version 3.2, to be removed in 4.0 alongside with magic methods below
     */
    private $renderer;

    public function __construct($renderer = null)
    {
        if ($renderer instanceof TwigRendererInterface) {
            @trigger_error(sprintf('Passing a Twig Form Renderer to the "%s" constructor is deprecated since Symfony 3.2 and won\'t be possible in 4.0. Pass the Twig\Environment to the TwigRendererEngine constructor instead.', static::class), E_USER_DEPRECATED);
        } elseif (null !== $renderer && !(\is_array($renderer) && isset($renderer[0], $renderer[1]) && $renderer[0] instanceof ContainerInterface)) {
            throw new \InvalidArgumentException(sprintf('Passing any arguments to the constructor of %s is reserved for internal use.', __CLASS__));
        }
        $this->renderer = $renderer;
    }

    /**
     * {@inheritdoc}
     *
     * To be removed in 4.0
     */
    public function initRuntime(Environment $environment)
    {
        if ($this->renderer instanceof TwigRendererInterface) {
            $this->renderer->setEnvironment($environment);
        } elseif (\is_array($this->renderer)) {
            $this->renderer[2] = $environment;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getTokenParsers()
    {
        return [
            // {% form_theme form "SomeBundle::widgets.twig" %}
            new FormThemeTokenParser(),
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function getFunctions()
    {
        return [
            new TwigFunction('form_widget', null, ['node_class' => 'Symfony\Bridge\Twig\Node\SearchAndRenderBlockNode', 'is_safe' => ['html']]),
            new TwigFunction('form_errors', null, ['node_class' => 'Symfony\Bridge\Twig\Node\SearchAndRenderBlockNode', 'is_safe' => ['html']]),
            new TwigFunction('form_label', null, ['node_class' => 'Symfony\Bridge\Twig\Node\SearchAndRenderBlockNode', 'is_safe' => ['html']]),
            new TwigFunction('form_row', null, ['node_class' => 'Symfony\Bridge\Twig\Node\SearchAndRenderBlockNode', 'is_safe' => ['html']]),
            new TwigFunction('form_rest', null, ['node_class' => 'Symfony\Bridge\Twig\Node\SearchAndRenderBlockNode', 'is_safe' => ['html']]),
            new TwigFunction('form', null, ['node_class' => 'Symfony\Bridge\Twig\Node\RenderBlockNode', 'is_safe' => ['html']]),
            new TwigFunction('form_start', null, ['node_class' => 'Symfony\Bridge\Twig\Node\RenderBlockNode', 'is_safe' => ['html']]),
            new TwigFunction('form_end', null, ['node_class' => 'Symfony\Bridge\Twig\Node\RenderBlockNode', 'is_safe' => ['html']]),
            new TwigFunction('csrf_token', ['Symfony\Component\Form\FormRenderer', 'renderCsrfToken']),
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function getFilters()
    {
        return [
            new TwigFilter('humanize', ['Symfony\Component\Form\FormRenderer', 'humanize']),
            new TwigFilter('form_encode_currency', ['Symfony\Component\Form\FormRenderer', 'encodeCurrency'], ['is_safe' => ['html'], 'needs_environment' => true]),
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function getTests()
    {
        return [
            new TwigTest('selectedchoice', 'Symfony\Bridge\Twig\Extension\twig_is_selected_choice'),
            new TwigTest('rootform', 'Symfony\Bridge\Twig\Extension\twig_is_root_form'),
        ];
    }

    /**
     * @internal
     */
    public function __get($name)
    {
        if ('renderer' === $name) {
            @trigger_error(sprintf('Using the "%s::$renderer" property is deprecated since Symfony 3.2 as it will be removed in 4.0.', __CLASS__), E_USER_DEPRECATED);

            if (\is_array($this->renderer)) {
                $renderer = $this->renderer[0]->get($this->renderer[1]);
                if (isset($this->renderer[2]) && $renderer instanceof TwigRendererInterface) {
                    $renderer->setEnvironment($this->renderer[2]);
                }
                $this->renderer = $renderer;
            }
        }

        return $this->$name;
    }

    /**
     * @internal
     */
    public function __set($name, $value)
    {
        if ('renderer' === $name) {
            @trigger_error(sprintf('Using the "%s::$renderer" property is deprecated since Symfony 3.2 as it will be removed in 4.0.', __CLASS__), E_USER_DEPRECATED);
        }

        $this->$name = $value;
    }

    /**
     * @internal
     */
    public function __isset($name)
    {
        if ('renderer' === $name) {
            @trigger_error(sprintf('Using the "%s::$renderer" property is deprecated since Symfony 3.2 as it will be removed in 4.0.', __CLASS__), E_USER_DEPRECATED);
        }

        return isset($this->$name);
    }

    /**
     * @internal
     */
    public function __unset($name)
    {
        if ('renderer' === $name) {
            @trigger_error(sprintf('Using the "%s::$renderer" property is deprecated since Symfony 3.2 as it will be removed in 4.0.', __CLASS__), E_USER_DEPRECATED);
        }

        unset($this->$name);
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'form';
    }
}

/**
 * Returns whether a choice is selected for a given form value.
 *
 * This is a function and not callable due to performance reasons.
 *
 * @param string|array $selectedValue The selected value to compare
 *
 * @return bool Whether the choice is selected
 *
 * @see ChoiceView::isSelected()
 */
function twig_is_selected_choice(ChoiceView $choice, $selectedValue)
{
    if (\is_array($selectedValue)) {
        return \in_array($choice->value, $selectedValue, true);
    }

    return $choice->value === $selectedValue;
}

/**
 * @internal
 */
function twig_is_root_form(FormView $formView)
{
    return null === $formView->parent;
}
