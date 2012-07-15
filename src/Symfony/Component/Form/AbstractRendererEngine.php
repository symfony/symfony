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
 * Default implementation of {@link FormRendererEngineInterface}.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
abstract class AbstractRendererEngine implements FormRendererEngineInterface
{
    /**
     * The variable in {@link FormViewInterface} used as cache key.
     */
    const CACHE_KEY_VAR = 'full_block_name';
    
    /**
     * @var array
     */
    protected $defaultThemes;

    /**
     * @var array
     */
    protected $themes = array();

    /**
     * @var array
     */
    protected $resources = array();

    /**
     * @var array
     */
    private $resourceHierarchyLevels = array();

    /**
     * Creates a new renderer engine.
     *
     * @param array $defaultThemes The default themes. The type of these
     *                             themes is open to the implementation.
     */
    public function __construct(array $defaultThemes = array())
    {
        $this->defaultThemes = $defaultThemes;
    }

    /**
     * {@inheritdoc}
     */
    public function setTheme(FormViewInterface $view, $themes)
    {
        $cacheKey = $view->getVar(self::CACHE_KEY_VAR);

        // Do not cast, as casting turns objects into arrays of properties
        $this->themes[$cacheKey] = is_array($themes) ? $themes : array($themes);
        $this->resources[$cacheKey] = array();
        $this->resourceHierarchyLevels[$cacheKey] = array();
    }

    /**
     * {@inheritdoc}
     */
    public function getResourceForBlock(FormViewInterface $view, $block)
    {
        $cacheKey = $view->getVar(self::CACHE_KEY_VAR);

        if (!isset($this->resources[$cacheKey][$block])) {
            $this->loadResourceForBlock($cacheKey, $view, $block);
        }

        return $this->resources[$cacheKey][$block];
    }

    /**
     * {@inheritdoc}
     */
    public function getResourceForBlockHierarchy(FormViewInterface $view, array $blockHierarchy, $hierarchyLevel)
    {
        $cacheKey = $view->getVar(self::CACHE_KEY_VAR);
        $block = $blockHierarchy[$hierarchyLevel];

        if (!isset($this->resources[$cacheKey][$block])) {
            $this->loadResourceForBlockHierarchy($cacheKey, $view, $blockHierarchy, $hierarchyLevel);
        }

        return $this->resources[$cacheKey][$block];
    }

    /**
     * {@inheritdoc}
     */
    public function getResourceHierarchyLevel(FormViewInterface $view, array $blockHierarchy, $hierarchyLevel)
    {
        $cacheKey = $view->getVar(self::CACHE_KEY_VAR);
        $block = $blockHierarchy[$hierarchyLevel];

        if (!isset($this->resources[$cacheKey][$block])) {
            $this->loadResourceForBlockHierarchy($cacheKey, $view, $blockHierarchy, $hierarchyLevel);
        }

        // If $block was previously rendered loaded with loadTemplateForBlock(), the template
        // is cached but the hierarchy level is not. In this case, we know that the  block
        // exists at this very hierarchy level, so we can just set it.
        if (!isset($this->resourceHierarchyLevels[$cacheKey][$block])) {
            $this->resourceHierarchyLevels[$cacheKey][$block] = $hierarchyLevel;
        }

        return $this->resourceHierarchyLevels[$cacheKey][$block];
    }

    /**
     * Loads the cache with the resource for a given block name.
     *
     * @see getResourceForBlock()
     *
     * @param string            $cacheKey The cache key of the form view.
     * @param FormViewInterface $view     The form view for finding the applying themes.
     * @param string            $block    The name of the block to load.
     *
     * @return Boolean True if the resource could be loaded, false otherwise.
     */
    abstract protected function loadResourceForBlock($cacheKey, FormViewInterface $view, $block);

    /**
     * Loads the cache with the resource for a specific level of a block hierarchy.
     *
     * @see getResourceForBlockHierarchy()
     *
     * @param string            $cacheKey       The cache key used for storing the
     *                                          resource.
     * @param FormViewInterface $view           The form view for finding the applying
     *                                          themes.
     * @param array             $blockHierarchy The block hierarchy, with the most
     *                                          specific block name at the end.
     * @param integer           $hierarchyLevel The level in the block hierarchy that
     *                                          should be loaded.
     *
     * @return Boolean True if the resource could be loaded, false otherwise.
     */
    private function loadResourceForBlockHierarchy($cacheKey, FormViewInterface $view, array $blockHierarchy, $hierarchyLevel)
    {
        $block = $blockHierarchy[$hierarchyLevel];

        // Try to find a template for that block
        if ($this->loadResourceForBlock($cacheKey, $view, $block)) {
            // If loadTemplateForBlock() returns true, it was able to populate the
            // cache. The only missing thing is to set the hierarchy level at which
            // the template was found.
            $this->resourceHierarchyLevels[$cacheKey][$block] = $hierarchyLevel;

            return true;
        }

        if ($hierarchyLevel > 0) {
            $parentLevel = $hierarchyLevel - 1;
            $parentBlock = $blockHierarchy[$parentLevel];

            // The next two if statements contain slightly duplicated code. This is by intention
            // and tries to avoid execution of unnecessary checks in order to increase performance.

            if (isset($this->resources[$cacheKey][$parentBlock])) {
                // It may happen that the parent block is already loaded, but its level is not.
                // In this case, the parent block must have been loaded by loadResourceForBlock(),
                // which does not check the hierarchy of the block. Subsequently the block must have
                // been found directly on the parent level.
                if (!isset($this->resourceHierarchyLevels[$cacheKey][$parentBlock])) {
                    $this->resourceHierarchyLevels[$cacheKey][$parentBlock] = $parentLevel;
                }

                // Cache the shortcuts for further accesses
                $this->resources[$cacheKey][$block] = $this->resources[$cacheKey][$parentBlock];
                $this->resourceHierarchyLevels[$cacheKey][$block] = $this->resourceHierarchyLevels[$cacheKey][$parentBlock];

                return true;
            }

            if ($this->loadResourceForBlockHierarchy($cacheKey, $view, $blockHierarchy, $parentLevel)) {
                // Cache the shortcuts for further accesses
                $this->resources[$cacheKey][$block] = $this->resources[$cacheKey][$parentBlock];
                $this->resourceHierarchyLevels[$cacheKey][$block] = $this->resourceHierarchyLevels[$cacheKey][$parentBlock];

                return true;
            }
        }

        // Cache the result for further accesses
        $this->resources[$cacheKey][$block] = false;
        $this->resourceHierarchyLevels[$cacheKey][$block] = false;

        return false;
    }
}
