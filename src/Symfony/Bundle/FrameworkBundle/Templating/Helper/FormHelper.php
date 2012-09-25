<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Templating\Helper;

use Symfony\Component\Templating\Helper\Helper;
use Symfony\Component\Form\FormRendererInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\Templating\EngineInterface;
use Symfony\Component\Form\Exception\FormException;
use Symfony\Component\Form\Extension\Csrf\CsrfProvider\CsrfProviderInterface;
use Symfony\Component\Form\Extension\Core\View\ChoiceView;
use Symfony\Component\Form\Util\FormUtil;

/**
 * FormHelper provides helpers to help display forms.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class FormHelper extends Helper
{
    /**
     * @var FormRendererInterface
     */
    private $renderer;

    /**
     * @param FormRendererInterface $renderer
     */
    public function __construct(FormRendererInterface $renderer)
    {
        $this->renderer = $renderer;
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'form';
    }

    /**
     * Sets a theme for a given view.
     *
     * The theme format is "<Bundle>:<Controller>".
     *
     * @param FormView     $view   A FormView instance
     * @param string|array $themes A theme or an array of theme
     */
    public function setTheme(FormView $view, $themes)
    {
        $this->renderer->setTheme($view, $themes);
    }

    /**
     * Renders the HTML enctype in the form tag, if necessary.
     *
     * Example usage templates:
     *
     *     <form action="..." method="post" <?php echo $view['form']->enctype() ?>>
     *
     * @param FormView $view The view for which to render the encoding type
     *
     * @return string The HTML markup
     */
    public function enctype(FormView $view)
    {
        return $this->renderer->searchAndRenderBlock($view, 'enctype');
    }

    /**
     * Renders the HTML for a given view.
     *
     * Example usage:
     *
     *     <?php echo view['form']->widget() ?>
     *
     * You can pass options during the call:
     *
     *     <?php echo view['form']->widget(array('attr' => array('class' => 'foo'))) ?>
     *
     *     <?php echo view['form']->widget(array('separator' => '+++++)) ?>
     *
     * @param FormView $view      The view for which to render the widget
     * @param array    $variables Additional variables passed to the template
     *
     * @return string The HTML markup
     */
    public function widget(FormView $view, array $variables = array())
    {
        return $this->renderer->searchAndRenderBlock($view, 'widget', $variables);
    }

    /**
     * Renders the entire form field "row".
     *
     * @param FormView $view      The view for which to render the row
     * @param array    $variables Additional variables passed to the template
     *
     * @return string The HTML markup
     */
    public function row(FormView $view, array $variables = array())
    {
        return $this->renderer->searchAndRenderBlock($view, 'row', $variables);
    }

    /**
     * Renders the label of the given view.
     *
     * @param FormView $view      The view for which to render the label
     * @param string   $label     The label
     * @param array    $variables Additional variables passed to the template
     *
     * @return string The HTML markup
     */
    public function label(FormView $view, $label = null, array $variables = array())
    {
        if (null !== $label) {
            $variables += array('label' => $label);
        }

        return $this->renderer->searchAndRenderBlock($view, 'label', $variables);
    }

    /**
     * Renders the errors of the given view.
     *
     * @param FormView $view The view to render the errors for
     *
     * @return string The HTML markup
     */
    public function errors(FormView $view)
    {
        return $this->renderer->searchAndRenderBlock($view, 'errors');
    }

    /**
     * Renders views which have not already been rendered.
     *
     * @param FormView $view      The parent view
     * @param array    $variables An array of variables
     *
     * @return string The HTML markup
     */
    public function rest(FormView $view, array $variables = array())
    {
        return $this->renderer->searchAndRenderBlock($view, 'rest', $variables);
    }

    /**
     * Renders a block of the template.
     *
     * @param FormView $view      The view for determining the used themes.
     * @param string   $blockName The name of the block to render.
     * @param array    $variables The variable to pass to the template.
     *
     * @return string The HTML markup
     */
    public function block(FormView $view, $blockName, array $variables = array())
    {
        return $this->renderer->renderBlock($view, $blockName, $variables);
    }

    /**
     * Returns a CSRF token.
     *
     * Use this helper for CSRF protection without the overhead of creating a
     * form.
     *
     * <code>
     * echo $view['form']->csrfToken('rm_user_'.$user->getId());
     * </code>
     *
     * Check the token in your action using the same intention.
     *
     * <code>
     * $csrfProvider = $this->get('form.csrf_provider');
     * if (!$csrfProvider->isCsrfTokenValid('rm_user_'.$user->getId(), $token)) {
     *     throw new \RuntimeException('CSRF attack detected.');
     * }
     * </code>
     *
     * @param string $intention The intention of the protected action
     *
     * @return string A CSRF token
     *
     * @throws \BadMethodCallException When no CSRF provider was injected in the constructor.
     */
    public function csrfToken($intention)
    {
        return $this->renderer->renderCsrfToken($intention);
    }

    public function humanize($text)
    {
        return $this->renderer->humanize($text);
    }
}
