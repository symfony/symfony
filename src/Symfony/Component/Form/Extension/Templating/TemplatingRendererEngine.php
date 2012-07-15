<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Extension\Templating;

use Symfony\Component\Form\AbstractRendererEngine;
use Symfony\Component\Form\FormViewInterface;
use Symfony\Component\Templating\EngineInterface;

/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class TemplatingRendererEngine extends AbstractRendererEngine
{
    /**
     * @var EngineInterface
     */
    private $engine;

    public function __construct(EngineInterface $engine, array $defaultThemes = array())
    {
        parent::__construct($defaultThemes);

        $this->engine = $engine;
    }

    /**
     * {@inheritdoc}
     */
    public function renderBlock(FormViewInterface $view, $resource, $block, array $variables = array())
    {
        return trim($this->engine->render($resource, $variables));
    }

    /**
     * Loads the cache with the resource for a given block name.
     *
     * This implementation tries to load as few blocks as possible, since each block
     * is represented by a template on the file system.
     *
     * @see getResourceForBlock()
     *
     * @param string            $cacheKey The cache key of the form view.
     * @param FormViewInterface $view     The form view for finding the applying themes.
     * @param string            $block    The name of the block to load.
     *
     * @return Boolean True if the resource could be loaded, false otherwise.
     */
    protected function loadResourceForBlock($cacheKey, FormViewInterface $view, $block)
    {
        // Recursively try to find the block in the themes assigned to $view,
        // then of its parent form, then of the parent form of the parent and so on.
        // When the root form is reached in this recursion, also the default
        // themes are taken into account.

        // Check each theme whether it contains the searched block
        if (isset($this->themes[$cacheKey])) {
            for ($i = count($this->themes[$cacheKey]) - 1; $i >= 0; --$i) {
                if ($this->loadResourceFromTheme($cacheKey, $block, $this->themes[$cacheKey][$i])) {
                    return true;
                }
            }
        }

        // Check the default themes once we reach the root form without success
        if (!$view->hasParent()) {
            for ($i = count($this->defaultThemes) - 1; $i >= 0; --$i) {
                if ($this->loadResourceFromTheme($cacheKey, $block, $this->defaultThemes[$i])) {
                    return true;
                }
            }
        }

        // If we did not find anything in the themes of the current view, proceed
        // with the themes of the parent view
        if ($view->hasParent()) {
            $parentCacheKey = $view->getParent()->getVar(self::CACHE_KEY_VAR);

            if (!isset($this->resources[$parentCacheKey][$block])) {
                $this->loadResourceForBlock($parentCacheKey, $view->getParent(), $block);
            }

            // If a template exists in the parent themes, cache that template
            // for the current theme as well to speed up further accesses
            if ($this->resources[$parentCacheKey][$block]) {
                $this->resources[$cacheKey][$block] = $this->resources[$parentCacheKey][$block];

                return true;
            }
        }

        // Cache that we didn't find anything to speed up further accesses
        $this->resources[$cacheKey][$block] = false;

        return false;
    }

    /**
     * Tries to load the resource for a block from a theme.
     *
     * @param string $cacheKey The cache key for storing the resource.
     * @param string $block    The name of the block to load a resource for.
     * @param mixed  $theme    The theme to load the block from.
     *
     * @return Boolean True if the resource could be loaded, false otherwise.
     */
    protected function loadResourceFromTheme($cacheKey, $block, $theme)
    {
        if ($this->engine->exists($templateName = $theme . ':' . $block . '.html.php')) {
            $this->resources[$cacheKey][$block] = $templateName;

            return true;
        }

        return false;
    }
}
