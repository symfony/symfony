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
     * @return FormRendererEngineInterface The renderer engine
     */
    public function getEngine();

    /**
     * Sets the theme(s) to be used for rendering a view and its children.
     *
     * @param FormView $view             The view to assign the theme(s) to
     * @param mixed    $themes           The theme(s). The type of these themes
     *                                   is open to the implementation.
     * @param bool     $useDefaultThemes If true, will use default themes specified
     *                                   in the renderer, will be added to the interface in 4.0
     */
    public function setTheme(FormView $view, $themes /*, $useDefaultThemes = true */);

    /**
     * Renders a named block of the form theme.
     *
     * @param FormView $view      The view for which to render the block
     * @param string   $blockName The name of the block
     * @param array    $variables The variables to pass to the template
     *
     * @return string The HTML markup
     */
    public function renderBlock(FormView $view, $blockName, array $variables = array());

    /**
     * Searches and renders a block for a given name suffix.
     *
     * The block is searched by combining the block names stored in the
     * form view with the given suffix. If a block name is found, that
     * block is rendered.
     *
     * If this method is called recursively, the block search is continued
     * where a block was found before.
     *
     * @param FormView $view            The view for which to render the block
     * @param string   $blockNameSuffix The suffix of the block name
     * @param array    $variables       The variables to pass to the template
     *
     * @return string The HTML markup
     */
    public function searchAndRenderBlock(FormView $view, $blockNameSuffix, array $variables = array());

    /**
     * Renders a CSRF token.
     *
     * Use this helper for CSRF protection without the overhead of creating a
     * form.
     *
     *     <input type="hidden" name="token" value="<?php $renderer->renderCsrfToken('rm_user_'.$user->getId()) ?>">
     *
     * Check the token in your action using the same token ID.
     *
     *     // $csrfProvider being an instance of Symfony\Component\Security\Csrf\TokenGenerator\TokenGeneratorInterface
     *     if (!$csrfProvider->isCsrfTokenValid('rm_user_'.$user->getId(), $token)) {
     *         throw new \RuntimeException('CSRF attack detected.');
     *     }
     *
     * @param string $tokenId The ID of the CSRF token
     *
     * @return string A CSRF token
     */
    public function renderCsrfToken($tokenId);

    /**
     * Makes a technical name human readable.
     *
     * Sequences of underscores are replaced by single spaces. The first letter
     * of the resulting string is capitalized, while all other letters are
     * turned to lowercase.
     *
     * @param string $text The text to humanize
     *
     * @return string The humanized text
     */
    public function humanize($text);
}
