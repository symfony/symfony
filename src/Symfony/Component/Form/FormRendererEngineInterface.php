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
 * Adapter for rendering form templates with a specific templating engine.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
interface FormRendererEngineInterface
{
    /**
     * Sets the theme(s) to be used for rendering a view and its children.
     *
     * @param FormView $view             The view to assign the theme(s) to
     * @param mixed    $themes           The theme(s). The type of these themes
     *                                   is open to the implementation.
     * @param bool     $useDefaultThemes If true, will use default themes specified
     *                                   in the engine
     */
    public function setTheme(FormView $view, $themes, $useDefaultThemes = true);

    /**
     * Returns the resource for a block name.
     *
     * The resource is first searched in the themes attached to $view, then
     * in the themes of its parent view and so on, until a resource was found.
     *
     * The type of the resource is decided by the implementation. The resource
     * is later passed to {@link renderBlock()} by the rendering algorithm.
     *
     * @param FormView $view      The view for determining the used themes.
     *                            First the themes attached directly to the
     *                            view with {@link setTheme()} are considered,
     *                            then the ones of its parent etc.
     * @param string   $blockName The name of the block to render
     *
     * @return mixed the renderer resource or false, if none was found
     */
    public function getResourceForBlockName(FormView $view, $blockName);

    /**
     * Returns the resource for a block hierarchy.
     *
     * A block hierarchy is an array which starts with the root of the hierarchy
     * and continues with the child of that root, the child of that child etc.
     * The following is an example for a block hierarchy:
     *
     *     form_widget
     *     text_widget
     *     url_widget
     *
     * In this example, "url_widget" is the most specific block, while the other
     * blocks are its ancestors in the hierarchy.
     *
     * The second parameter $hierarchyLevel determines the level of the hierarchy
     * that should be rendered. For example, if $hierarchyLevel is 2 for the
     * above hierarchy, the engine will first look for the block "url_widget",
     * then, if that does not exist, for the block "text_widget" etc.
     *
     * The type of the resource is decided by the implementation. The resource
     * is later passed to {@link renderBlock()} by the rendering algorithm.
     *
     * @param FormView $view               The view for determining the used themes.
     *                                     First the themes  attached directly to
     *                                     the view with {@link setTheme()} are
     *                                     considered, then the ones of its parent etc.
     * @param array    $blockNameHierarchy The block name hierarchy, with the root block
     *                                     at the beginning
     * @param int      $hierarchyLevel     The level in the hierarchy at which to start
     *                                     looking. Level 0 indicates the root block, i.e.
     *                                     the first element of $blockNameHierarchy.
     *
     * @return mixed The renderer resource or false, if none was found
     */
    public function getResourceForBlockNameHierarchy(FormView $view, array $blockNameHierarchy, $hierarchyLevel);

    /**
     * Returns the hierarchy level at which a resource can be found.
     *
     * A block hierarchy is an array which starts with the root of the hierarchy
     * and continues with the child of that root, the child of that child etc.
     * The following is an example for a block hierarchy:
     *
     *     form_widget
     *     text_widget
     *     url_widget
     *
     * The second parameter $hierarchyLevel determines the level of the hierarchy
     * that should be rendered.
     *
     * If we call this method with the hierarchy level 2, the engine will first
     * look for a resource for block "url_widget". If such a resource exists,
     * the method returns 2. Otherwise it tries to find a resource for block
     * "text_widget" (at level 1) and, again, returns 1 if a resource was found.
     * The method continues to look for resources until the root level was
     * reached and nothing was found. In this case false is returned.
     *
     * The type of the resource is decided by the implementation. The resource
     * is later passed to {@link renderBlock()} by the rendering algorithm.
     *
     * @param FormView $view               The view for determining the used themes.
     *                                     First the themes  attached directly to
     *                                     the view with {@link setTheme()} are
     *                                     considered, then the ones of its parent etc.
     * @param array    $blockNameHierarchy The block name hierarchy, with the root block
     *                                     at the beginning
     * @param int      $hierarchyLevel     The level in the hierarchy at which to start
     *                                     looking. Level 0 indicates the root block, i.e.
     *                                     the first element of $blockNameHierarchy.
     *
     * @return int|bool The hierarchy level or false, if no resource was found
     */
    public function getResourceHierarchyLevel(FormView $view, array $blockNameHierarchy, $hierarchyLevel);

    /**
     * Renders a block in the given renderer resource.
     *
     * The resource can be obtained by calling {@link getResourceForBlock()}
     * or {@link getResourceForBlockHierarchy()}. The type of the resource is
     * decided by the implementation.
     *
     * @param FormView $view      The view to render
     * @param mixed    $resource  The renderer resource
     * @param string   $blockName The name of the block to render
     * @param array    $variables The variables to pass to the template
     *
     * @return string The HTML markup
     */
    public function renderBlock(FormView $view, $resource, $blockName, array $variables = []);
}
