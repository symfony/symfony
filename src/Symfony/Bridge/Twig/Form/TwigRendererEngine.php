<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Twig\Form;

use Symfony\Component\Form\AbstractRendererEngine;
use Symfony\Component\Form\FormView;
use Twig\BlockChain;
use Twig\Environment;
use Twig\Template;

/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class TwigRendererEngine extends AbstractRendererEngine
{
    private bool $useChainLookup;
    private Template $template;

    /**
     * @var array<string, BlockChain|null>
     */
    private array $chains = [];

    private ?BlockChain $defaultChain = null;

    public function __construct(
        array $defaultThemes,
        private Environment $environment,
    ) {
        parent::__construct($defaultThemes);

        // a child class that loads resources its own way keeps the cached lookups of the parent class
        $this->useChainLookup = self::class === (new \ReflectionMethod($this, 'loadResourceForBlockName'))->class;
    }

    public function setTheme(FormView $view, mixed $themes, bool $useDefaultThemes = true): void
    {
        unset($this->chains[$view->vars[self::CACHE_KEY_VAR]]);

        parent::setTheme($view, $themes, $useDefaultThemes);
    }

    public function reset(): void
    {
        $this->chains = [];

        parent::reset();
    }

    public function getResourceForBlockNameHierarchy(FormView $view, array $blockNameHierarchy, int $hierarchyLevel): mixed
    {
        if (!$this->useChainLookup) {
            return parent::getResourceForBlockNameHierarchy($view, $blockNameHierarchy, $hierarchyLevel);
        }

        $chain = $this->getChain($view->vars[self::CACHE_KEY_VAR], $view);

        return false !== $this->findHierarchyLevel($chain, $blockNameHierarchy, $hierarchyLevel) ? $chain : false;
    }

    public function getResourceHierarchyLevel(FormView $view, array $blockNameHierarchy, int $hierarchyLevel): int|false
    {
        if (!$this->useChainLookup) {
            return parent::getResourceHierarchyLevel($view, $blockNameHierarchy, $hierarchyLevel);
        }

        return $this->findHierarchyLevel($this->getChain($view->vars[self::CACHE_KEY_VAR], $view), $blockNameHierarchy, $hierarchyLevel);
    }

    public function renderBlock(FormView $view, mixed $resource, string $blockName, array $variables = []): string
    {
        if ($resource instanceof BlockChain) {
            return $resource->renderBlock($blockName, $variables);
        }

        $cacheKey = $view->vars[self::CACHE_KEY_VAR];

        $context = $variables + $this->environment->getGlobals();

        ob_start();

        // By contract,This method can only be called after getting the resource
        // (which is passed to the method). Getting a resource for the first time
        // (with an empty cache) is guaranteed to invoke loadResourcesFromTheme(),
        // where the property $template is initialized.

        // We do not call renderBlock here to avoid too many nested level calls
        // (XDebug limits the level to 100 by default)
        $this->template->displayBlock($blockName, $context, $this->resources[$cacheKey]);

        return ob_get_clean();
    }

    /**
     * Loads the cache with the resource for a given block name.
     *
     * @see getResourceForBlock()
     */
    protected function loadResourceForBlockName(string $cacheKey, FormView $view, string $blockName): bool
    {
        $chain = $this->getChain($cacheKey, $view);

        $this->resources[$cacheKey][$blockName] = $chain?->hasBlock($blockName) ? $chain : false;
        $this->setResourceInheritability($cacheKey, $blockName, true);

        return false !== $this->resources[$cacheKey][$blockName];
    }

    /**
     * Returns the chain composing the blocks of every theme that applies to a view.
     *
     * Composing the whole theme stack into a single chain is what allows a block to
     * call a block defined by another theme of the stack.
     */
    private function getChain(string $cacheKey, FormView $view): ?BlockChain
    {
        if (\array_key_exists($cacheKey, $this->chains)) {
            return $this->chains[$cacheKey];
        }

        // The themes of the parent view apply to this one, and the default themes
        // apply once the root view is reached.
        if ($view->parent) {
            $inherited = $this->getChain($view->parent->vars[self::CACHE_KEY_VAR], $view->parent);
        } elseif ((!isset($this->useDefaultThemes[$cacheKey]) || $this->useDefaultThemes[$cacheKey]) && $this->defaultThemes) {
            $inherited = $this->defaultChain ??= new BlockChain($this->environment, array_reverse($this->defaultThemes));
        } else {
            $inherited = null;
        }

        // A view without themes of its own applies exactly the chain it inherits, so
        // the whole form usually shares the chain built for the default themes.
        if (!$themes = array_reverse($this->themes[$cacheKey] ?? [])) {
            return $this->chains[$cacheKey] = $inherited;
        }

        if ($inherited) {
            $themes[] = $inherited;
        }

        return $this->chains[$cacheKey] = new BlockChain($this->environment, $themes);
    }

    /**
     * Loads the resources for all blocks in a theme.
     *
     * @param mixed $theme The theme to load the block from. This parameter
     *                     is passed by reference, because it might be necessary
     *                     to initialize the theme first. Any changes made to
     *                     this variable will be kept and be available upon
     *                     further calls to this method using the same theme.
     */
    protected function loadResourcesFromTheme(string $cacheKey, mixed &$theme): void
    {
        if (!$theme instanceof Template) {
            $theme = $this->environment->load($theme)->unwrap();
        }

        // Store the first Template instance that we find so that
        // we can call displayBlock() later on. It doesn't matter *which*
        // template we use for that, since we pass the used blocks manually
        // anyway.
        $this->template ??= $theme;

        // Use a separate variable for the inheritance traversal, because
        // theme is a reference and we don't want to change it.
        $currentTheme = $theme;

        $context = $this->environment->getGlobals();

        // The do loop takes care of template inheritance.
        // Add blocks from all templates in the inheritance tree, but avoid
        // overriding blocks already set.
        do {
            foreach ($currentTheme->getBlocks() as $block => $blockData) {
                if (!isset($this->resources[$cacheKey][$block])) {
                    // The resource given back is the key to the bucket that
                    // contains this block.
                    $this->resources[$cacheKey][$block] = $blockData;
                    $this->setResourceInheritability($cacheKey, $block, true);
                }
            }
        } while (false !== $currentTheme = $currentTheme->getParent($context));
    }

    /**
     * Finds the closest level of a block hierarchy that the chain defines a block for.
     */
    private function findHierarchyLevel(?BlockChain $chain, array $blockNameHierarchy, int $hierarchyLevel): int|false
    {
        if (null === $chain) {
            return false;
        }

        for (; $hierarchyLevel >= 0; --$hierarchyLevel) {
            if ($chain->hasBlock($blockNameHierarchy[$hierarchyLevel])) {
                return $hierarchyLevel;
            }
        }

        return false;
    }
}
