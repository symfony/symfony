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
use Symfony\Component\Form\FormViewInterface;
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
     * @var EngineInterface
     */
    private $engine;

    /**
     * @var CsrfProviderInterface
     */
    private $csrfProvider;

    /**
     * @var array
     */
    private $blockHierarchyMap = array();

    /**
     * @var array
     */
    private $currentHierarchyLevelMap = array();

    /**
     * @var array
     */
    private $variableMap = array();

    /**
     * @var array
     */
    private $stack = array();

    /**
     * @var array
     */
    private $defaultThemes;

    /**
     * @var array
     */
    private $themes = array();

    /**
     * @var array
     */
    private $templateCache = array();

    /**
     * @var array
     */
    private $templateHierarchyLevelCache = array();

    /**
     * Constructor.
     *
     * @param EngineInterface       $engine       The templating engine
     * @param CsrfProviderInterface $csrfProvider The CSRF provider
     * @param array                 $defaultThemes    An array of theme names
     */
    public function __construct(EngineInterface $engine, CsrfProviderInterface $csrfProvider = null, array $defaultThemes = array())
    {
        $this->engine = $engine;
        $this->csrfProvider = $csrfProvider;
        $this->defaultThemes = $defaultThemes;
    }

    public function isChoiceGroup($label)
    {
        return FormUtil::isChoiceGroup($label);
    }

    public function isChoiceSelected(FormViewInterface $view, ChoiceView $choice)
    {
        return FormUtil::isChoiceSelected($choice->getValue(), $view->getVar('value'));
    }

    /**
     * Sets a theme for a given view.
     *
     * The theme format is "<Bundle>:<Controller>".
     *
     * @param FormViewInterface     $view   A FormViewInterface instance
     * @param string|array $themes A theme or an array of theme
     */
    public function setTheme(FormViewInterface $view, $themes)
    {
        $this->themes[$view->getVar('full_block_name')] = (array) $themes;
        $this->templateCache = array();
        $this->templateHierarchyLevelCache = array();
    }

    /**
     * Renders the HTML enctype in the form tag, if necessary.
     *
     * Example usage templates:
     *
     *     <form action="..." method="post" <?php echo $view['form']->enctype() ?>>
     *
     * @param FormViewInterface $view The view for which to render the encoding type
     *
     * @return string The HTML markup
     */
    public function enctype(FormViewInterface $view)
    {
        return $this->renderSection($view, 'enctype');
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
     * @param FormViewInterface $view      The view for which to render the widget
     * @param array    $variables Additional variables passed to the template
     *
     * @return string The HTML markup
     */
    public function widget(FormViewInterface $view, array $variables = array())
    {
        return $this->renderSection($view, 'widget', $variables);
    }

    /**
     * Renders the entire form field "row".
     *
     * @param FormViewInterface $view      The view for which to render the row
     * @param array    $variables Additional variables passed to the template
     *
     * @return string The HTML markup
     */
    public function row(FormViewInterface $view, array $variables = array())
    {
        return $this->renderSection($view, 'row', $variables);
    }

    /**
     * Renders the label of the given view.
     *
     * @param FormViewInterface $view      The view for which to render the label
     * @param string   $label     The label
     * @param array    $variables Additional variables passed to the template
     *
     * @return string The HTML markup
     */
    public function label(FormViewInterface $view, $label = null, array $variables = array())
    {
        if ($label !== null) {
            $variables += array('label' => $label);
        }

        return $this->renderSection($view, 'label', $variables);
    }

    /**
     * Renders the errors of the given view.
     *
     * @param FormViewInterface $view The view to render the errors for
     *
     * @return string The HTML markup
     */
    public function errors(FormViewInterface $view)
    {
        return $this->renderSection($view, 'errors');
    }

    /**
     * Renders views which have not already been rendered.
     *
     * @param FormViewInterface $view      The parent view
     * @param array    $variables An array of variables
     *
     * @return string The HTML markup
     */
    public function rest(FormViewInterface $view, array $variables = array())
    {
        return $this->renderSection($view, 'rest', $variables);
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
        if (!$this->csrfProvider instanceof CsrfProviderInterface) {
            throw new \BadMethodCallException('CSRF token can only be generated if a CsrfProviderInterface is injected in the constructor.');
        }

        return $this->csrfProvider->generateCsrfToken($intention);
    }

    /**
     * Renders a template.
     *
     * 1. This function first looks for a block named "_<view id>_<section>",
     * 2. if such a block is not found the function will look for a block named
     *    "<type name>_<section>",
     * 3. the type name is recursively replaced by the parent type name until a
     *    corresponding block is found
     *
     * @param FormViewInterface $view      The form view
     * @param string   $section   The section to render (i.e. 'row', 'widget', 'label', ...)
     * @param array    $variables Additional variables
     *
     * @return string The HTML markup
     *
     * @throws FormException if no template block exists to render the given section of the view
     */
    protected function renderSection(FormViewInterface $view, $section, array $variables = array())
    {
        $renderOnlyOnce = in_array($section, array('row', 'widget'));

        if ($renderOnlyOnce && $view->isRendered()) {
            return '';
        }

        // The cache key for storing the variables and types
        $mapKey = $view->getVar('full_block_name') . '_' . $section;

        // In templates, we have to deal with two kinds of block hierarchies:
        //
        //   +---------+          +---------+
        //   | Theme B | -------> | Theme A |
        //   +---------+          +---------+
        //
        //   form_widget -------> form_widget
        //       ^
        //       |
        //  choice_widget -----> choice_widget
        //
        // The first kind of hierarchy is the theme hierarchy. This allows to
        // override the block "choice_widget" from Theme A in the extending
        // Theme B. This kind of inheritance needs to be supported by the
        // template engine and, for example, offers "parent()" or similar
        // functions to fall back from the custom to the parent implementation.
        //
        // The second kind of hierarchy is the form type hierarchy. This allows
        // to implement a custom "choice_widget" block (no matter in which theme),
        // or to fallback to the block of the parent type, which would be
        // "form_widget" in this example (again, no matter in which theme).
        // If the designer wants to explicitely fallback to "form_widget" in his
        // custom "choice_widget", for example because he only wants to wrap
        // a <div> around the original implementation, he can simply call the
        // widget() function again to render the block for the parent type.
        //
        // The second kind is implemented in the following blocks.
        if (!isset($this->blockHierarchyMap[$mapKey])) {
            // INITIAL CALL
            // Calculate the hierarchy of template blocks and start on
            // the bottom level of the hierarchy (= "_<id>_<section>" block)
            $blockHierarchy = array_map(function ($type) use ($section) {
                return $type . '_' . $section;
            }, $view->getVar('types'));
            $blockHierarchy[] = $view->getVar('full_block_name') . '_' . $section;
            $currentHierarchyLevel = count($blockHierarchy) - 1;

            // The default variable scope contains all view variables, merged with
            // the variables passed explicitely to the helper
            $variables = array_replace_recursive($view->getVars(), $variables);
        } else {
            // RECURSIVE CALL
            // If a block recursively calls renderSection() again, resume rendering
            // using the parent type in the hierarchy.
            $blockHierarchy = $this->blockHierarchyMap[$mapKey];
            $currentHierarchyLevel = $this->currentHierarchyLevelMap[$mapKey] - 1;

            // Reuse the current scope and merge it with the explicitely passed variables
            $variables = array_replace_recursive($this->variableMap[$mapKey], $variables);
        }

        $cacheKey = $view->getVar('full_block_name');
        $block = $blockHierarchy[$currentHierarchyLevel];

        // Populate the cache if the template for the block is not known yet
        if (!isset($this->templateCache[$cacheKey][$block])) {
            $this->loadTemplateForBlockHierarchy($view, $blockHierarchy, $currentHierarchyLevel);
        }

        // Escape if no template exists for this block
        if (!$this->templateCache[$cacheKey][$block]) {
            throw new FormException(sprintf(
                'Unable to render the form as none of the following blocks exist: "%s".',
                implode('", "', array_reverse($blockHierarchy))
            ));
        }

        // If $block was previously rendered manually with renderBlock(), the template
        // is cached but the hierarchy level is not. In this case, we know that the  block
        // exists at this very hierarchy level (renderBlock() does not traverse the hierarchy)
        // so we can just set it.
        if (!isset($this->templateHierarchyLevelCache[$cacheKey][$block])) {
            $this->templateHierarchyLevelCache[$cacheKey][$block] = $currentHierarchyLevel;
        }

        // In order to make recursive calls possible, we need to store the block hierarchy,
        // the current level of the hierarchy and the variables so that this method can
        // resume rendering one level higher of the hierarchy when it is called recursively.
        //
        // We need to store these values in maps (associative arrays) because within a
        // call to widget() another call to widget() can be made, but for a different view
        // object. These nested calls should not override each other.
        $this->blockHierarchyMap[$mapKey] = $blockHierarchy;
        $this->currentHierarchyLevelMap[$mapKey] = $this->templateHierarchyLevelCache[$cacheKey][$block];
        $this->variableMap[$mapKey] = $variables;

        // We also need to store the view and the variables so that we can render custom
        // blocks with renderBlock() using the same themes and variables as in the outer
        // block.
        //
        // A stack is sufficient for this purpose, because renderBlock() always accesses
        // the immediate next outer scope, which is always stored at the end of the stack.
        $this->stack[] = array($view, $variables);

        // Do the rendering
        $html = $this->engine->render($this->templateCache[$cacheKey][$block], $variables);

        // Clear the stack
        array_pop($this->stack);

        // Clear the maps
        unset($this->blockHierarchyMap[$mapKey]);
        unset($this->currentHierarchyLevelMap[$mapKey]);
        unset($this->variableMap[$mapKey]);

        if ($renderOnlyOnce) {
            $view->setRendered();
        }

        return trim($html);
    }

    public function renderBlock($block, $variables = array())
    {
        if (0 == count($this->stack)) {
            throw new FormException('This method should only be called while rendering a form element.');
        }

        list($view, $scopeVariables) = end($this->stack);

        $cacheKey = $view->getVar('full_block_name');

        if (!isset($this->templateCache[$cacheKey][$block]) && !$this->loadTemplateForBlock($view, $block)) {
            throw new FormException(sprintf('No block "%s" found while rendering the form.', $block));
        }

        $variables = array_replace_recursive($scopeVariables, $variables);

        return trim($this->engine->render($this->templateCache[$cacheKey][$block], $variables));
    }

    public function humanize($text)
    {
        return ucfirst(trim(strtolower(preg_replace('/[_\s]+/', ' ', $text))));
    }

    public function getName()
    {
        return 'form';
    }

    /**
     * Loads the cache with the template for a specific level of a block hierarchy.
     *
     * For example, the block hierarchy could be:
     *
     * <code>
     * form_widget
     * choice_widget
     * entity_widget
     * </code
     *
     * When loading this hierarchy at index 1, the method first tries to find the
     * block "choice_widget" in any of the themes assigned to $view. If nothing is
     * found, it then continues to look for "form_widget" and so on.
     *
     * This method both stores the template name and the level in the hierarchy at
     * which the template was found in the cache. In the above example, if the
     * template "MyBundle:choice_widget.html.php" was found at level 1, this template
     * and the level "1" are stored. The stored level helps to resume rendering
     * in recursive calls, where the parent block needs to be rendered (here the
     * block "form_widget" at level 0).
     *
     * @param FormViewInterface $view           The form view for finding the applying themes.
     * @param array             $blockHierarchy The block hierarchy, with the most specific block name at the end.
     * @param integer           $currentLevel   The level in the block hierarchy that should be loaded.
     *
     * @return Boolean True if the cache could be populated successfully, false otherwise.
     */
    private function loadTemplateForBlockHierarchy(FormViewInterface $view, array $blockHierarchy, $currentLevel)
    {
        $cacheKey = $view->getVar('full_block_name');
        $block = $blockHierarchy[$currentLevel];

        // Try to find a template for that block
        if ($this->loadTemplateForBlock($view, $block)) {
            // If loadTemplateForBlock() returns true, it was able to populate the
            // cache. The only missing thing is to set the hierarchy level at which
            // the template was found.
            $this->templateHierarchyLevelCache[$cacheKey][$block] = $currentLevel;

            return true;
        }

        if ($currentLevel > 0) {
            $parentLevel = $currentLevel - 1;
            $parentBlock = $blockHierarchy[$parentLevel];

            if ($this->loadTemplateForBlockHierarchy($view, $blockHierarchy, $parentLevel)) {
                // Cache the shortcuts for further accesses
                $this->templateCache[$cacheKey][$block] = $this->templateCache[$cacheKey][$parentBlock];
                $this->templateHierarchyLevelCache[$cacheKey][$block] = $this->templateHierarchyLevelCache[$cacheKey][$parentBlock];

                return true;
            }
        }

        // Cache the result for further accesses
        $this->templateCache[$cacheKey][$block] = false;
        $this->templateHierarchyLevelCache[$cacheKey][$block] = false;

        return false;
    }

    /**
     * Loads the cache with the template for a given block name.
     *
     * The template is first searched in all the themes assigned to $view. If nothing
     * is found, the search is continued in the themes of the parent view. Once arrived
     * at the root view, if still nothing has been found, the default themes stored
     * in this class are searched.
     *
     * @param FormViewInterface $view  The form view for finding the applying themes.
     * @param string            $block The name of the block to load.
     *
     * @return Boolean True if the cache could be populated successfully, false otherwise.
     */
    private function loadTemplateForBlock(FormViewInterface $view, $block)
    {
        // Recursively try to find the block in the themes assigned to $view,
        // then of its parent form, then of the parent form of the parent and so on.
        // When the root form is reached in this recursion, also the default
        // themes are taken into account.
        $cacheKey = $view->getVar('full_block_name');

        // Check the default themes once we reach the root form without success
        $themes = $view->hasParent() ? array() : $this->defaultThemes;

        // Add the themes that have been registered for that specific element
        if (isset($this->themes[$cacheKey])) {
            $themes = array_merge($themes, $this->themes[$cacheKey]);
        }

        // Check each theme whether it contains the searched block
        for ($i = count($themes) - 1; $i >= 0; --$i) {
            if ($this->engine->exists($templateName = $themes[$i] . ':' . $block . '.html.php')) {
                $this->templateCache[$cacheKey][$block] = $templateName;

                return true;
            }
        }

        // If we did not find anything in the themes of the current view, proceed
        // with the themes of the parent view
        if ($view->hasParent()) {
            $parentCacheKey = $view->getParent()->getVar('full_block_name');

            if (!isset($this->templateCache[$parentCacheKey][$block])) {
                $this->loadTemplateForBlock($view->getParent(), $block);
            }

            // If a template exists in the parent themes, cache that template name
            // for the current theme as well to speed up further accesses
            if ($this->templateCache[$parentCacheKey][$block]) {
                $this->templateCache[$cacheKey][$block] = $this->templateCache[$parentCacheKey][$block];

                return true;
            }
        }

        // Cache that we didn't find anything to speed up further accesses
        $this->templateCache[$cacheKey][$block] = false;

        return false;
    }
}
