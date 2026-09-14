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
    private bool $useBlockChain;
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

        $this->useBlockChain = class_exists(BlockChain::class);
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
        if ($this->useBlockChain) {
            $chain = $this->getChain($cacheKey, $view);

            $this->resources[$cacheKey][$blockName] = $chain?->hasBlock($blockName) ? $chain : false;
            $this->setResourceInheritability($cacheKey, $blockName, true);

            return false !== $this->resources[$cacheKey][$blockName];
        }

        // The caller guarantees that $this->resources[$cacheKey][$block] is
        // not set, but it doesn't have to check whether $this->resources[$cacheKey]
        // is set. If $this->resources[$cacheKey] is set, all themes for this
        // $cacheKey are already loaded (due to the eager population, see the doc
        // comment of loadResourcesFromThemes()).
        if (isset($this->resources[$cacheKey])) {
            // As said in the previous, the caller guarantees that
            // $this->resources[$cacheKey][$block] is not set. Since the themes are
            // already loaded, it can only be a non-existing block.
            $this->resources[$cacheKey][$blockName] = false;
            $this->setResourceInheritability($cacheKey, $blockName, true);

            return false;
        }

        $this->loadResourcesFromThemes($cacheKey, $view, $blockName);

        // Even though we loaded the themes, it can happen that none of them
        // contains the searched block
        if (!isset($this->resources[$cacheKey][$blockName])) {
            // Cache that we didn't find anything to speed up further accesses
            $this->resources[$cacheKey][$blockName] = false;
            $this->setResourceInheritability($cacheKey, $blockName, true);
        }

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
     * Loads the resources for all blocks of the themes assigned to the given view.
     *
     * This implementation eagerly loads all blocks of the themes assigned to the given view
     * and all of its ancestors views. This is necessary, because Twig receives the
     * list of blocks later. At that point, all blocks must already be loaded, for the
     * case that the function "block()" is used in the Twig template.
     */
    private function loadResourcesFromThemes(string $cacheKey, FormView $view, string $blockName): void
    {
        // Check each theme whether it contains the searched block
        if (isset($this->themes[$cacheKey])) {
            for ($i = \count($this->themes[$cacheKey]) - 1; $i >= 0; --$i) {
                $this->loadResourcesFromTheme($cacheKey, $this->themes[$cacheKey][$i]);
                // CONTINUE LOADING (see doc comment)
            }
        }

        // Check the default themes once we reach the root view without success
        if (!$view->parent) {
            if (!isset($this->useDefaultThemes[$cacheKey]) || $this->useDefaultThemes[$cacheKey]) {
                for ($i = \count($this->defaultThemes) - 1; $i >= 0; --$i) {
                    $this->loadResourcesFromTheme($cacheKey, $this->defaultThemes[$i]);
                    // CONTINUE LOADING (see doc comment)
                }
            }
        }

        // Proceed with the themes of the parent view
        if ($view->parent) {
            $parentCacheKey = $view->parent->vars[self::CACHE_KEY_VAR];

            if (!isset($this->resources[$parentCacheKey])) {
                $this->loadResourceForBlockName($parentCacheKey, $view->parent, $blockName);
            }

            // EAGER CACHE POPULATION (see doc comment)
            foreach ($this->resources[$parentCacheKey] as $nestedBlockName => $resource) {
                if (!isset($this->resources[$cacheKey][$nestedBlockName]) && $this->isResourceInheritable($parentCacheKey, $nestedBlockName)) {
                    $this->resources[$cacheKey][$nestedBlockName] = $resource;
                    $this->setResourceInheritability($cacheKey, $nestedBlockName, true);
                }
            }
        }
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
}
