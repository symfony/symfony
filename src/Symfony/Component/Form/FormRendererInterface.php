<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form;

use Symfony\Component\Form\Extension\Core\View\ChoiceView;

/**
 * Renders a form into HTML.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
interface FormRendererInterface
{
    /**
     * Returns the engine used by this renderer.
     *
     * @return FormRendererEngineInterface The renderer engine.
     */
    public function getEngine();

    /**
     * Sets the theme(s) to be used for rendering a view and its children.
     *
     * @param FormView $view   The view to assign the theme(s) to.
     * @param mixed             $themes The theme(s). The type of these themes
     *                                  is open to the implementation.
     */
    public function setTheme(FormView $view, $themes);

    /**
     * Renders the HTML enctype in the form tag, if necessary.
     *
     * Example usage templates:
     *
     *     <form action="..." method="post" <?php echo $renderer->renderEnctype($form) ?>>
     *
     * @param FormView $view The view for which to render the encoding type
     *
     * @return string The HTML markup
     */
    public function renderEnctype(FormView $view);

    /**
     * Renders the entire row for a form field.
     *
     * A row typically contains the label, errors and widget of a field.
     *
     * @param FormView $view      The view for which to render the row
     * @param array             $variables Additional variables passed to the template
     *
     * @return string The HTML markup
     */
    public function renderRow(FormView $view, array $variables = array());

    /**
     * Renders views which have not already been rendered.
     *
     * @param FormView $view      The parent view
     * @param array             $variables An array of variables
     *
     * @return string The HTML markup
     */
    public function renderRest(FormView $view, array $variables = array());

    /**
     * Renders the HTML for a given view.
     *
     * Example usage:
     *
     *     <?php echo $renderer->renderWidget($form) ?>
     *
     * You can pass options during the call:
     *
     *     <?php echo $renderer->renderWidget($form, array('attr' => array('class' => 'foo'))) ?>
     *
     *     <?php echo $renderer->renderWidget($form, array('separator' => '+++++)) ?>
     *
     * @param FormView $view      The view for which to render the widget
     * @param array             $variables Additional variables passed to the template
     *
     * @return string The HTML markup
     */
    public function renderWidget(FormView $view, array $variables = array());

    /**
     * Renders the errors of the given view.
     *
     * @param FormView $view The view to render the errors for
     *
     * @return string The HTML markup
     */
    public function renderErrors(FormView $view);

    /**
     * Renders the label of the given view.
     *
     * @param FormView $view      The view for which to render the label
     * @param string            $label     The label
     * @param array             $variables Additional variables passed to the template
     *
     * @return string The HTML markup
     */
    public function renderLabel(FormView $view, $label = null, array $variables = array());

    /**
     * Renders a named block of the form theme.
     *
     * @param string $block     The name of the block.
     * @param array  $variables The variables to pass to the template.
     *
     * @return string The HTML markup
     */
    public function renderBlock($block, array $variables = array());

    /**
     * Renders a CSRF token.
     *
     * Use this helper for CSRF protection without the overhead of creating a
     * form.
     *
     * <code>
     * <input type="hidden" name="token" value="<?php $renderer->renderCsrfToken('rm_user_'.$user->getId()) ?>">
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
     */
    public function renderCsrfToken($intention);

    /**
     * Makes a technical name human readable.
     *
     * Sequences of underscores are replaced by single spaces. The first letter
     * of the resulting string is capitalized, while all other letters are
     * turned to lowercase.
     *
     * @param string $text The text to humanize.
     *
     * @return string The humanized text.
     */
    public function humanize($text);
}
