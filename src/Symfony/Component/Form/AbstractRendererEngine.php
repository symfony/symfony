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
     * The variable in {@link FormView} used as cache key.
     */
    public const CACHE_KEY_VAR = 'cache_key';

    /**
     * @var array
     */
    protected $defaultThemes;

    /**
     * @var array[]
     */
    protected $themes = [];

    /**
     * @var bool[]
     */
    protected $useDefaultThemes = [];

    /**
     * @var array[]
     */
    protected $resources = [];

    /**
     * @var array<array<int|false>>
     */
    private $resourceHierarchyLevels = [];

    /**
     * Creates a new renderer engine.
     *
     * @param array $defaultThemes The default themes. The type of these
     *                             themes is open to the implementation.
     */
    public function __construct(array $defaultThemes = [])
    {
        $this->defaultThemes = $defaultThemes;
    }

    /**
     * {@inheritdoc}
     */
    public function setTheme(FormView $view, $themes, $useDefaultThemes = true)
    {
        $cacheKey = $view->vars[self::CACHE_KEY_VAR];

        // Do not cast, as casting turns objects into arrays of properties
        $this->themes[$cacheKey] = \is_array($themes) ? $themes : [$themes];
        $this->useDefaultThemes[$cacheKey] = (bool) $useDefaultThemes;

        // Unset instead of resetting to an empty array, in order to allow
        // implementations (like TwigRendererEngine) to check whether $cacheKey
        // is set at all.
        unset($this->resources[$cacheKey], $this->resourceHierarchyLevels[$cacheKey]);
    }

    /**
     * {@inheritdoc}
     */
    public function getResourceForBlockName(FormView $view, $blockName)
    {
        $cacheKey = $view->vars[self::CACHE_KEY_VAR];

        if (!isset($this->resources[$cacheKey][$blockName])) {
            $this->loadResourceForBlockName($cacheKey, $view, $blockName);
        }

        return $this->resources[$cacheKey][$blockName];
    }

    /**
     * {@inheritdoc}
     */
    public function getResourceForBlockNameHierarchy(FormView $view, array $blockNameHierarchy, $hierarchyLevel)
    {
        $cacheKey = $view->vars[self::CACHE_KEY_VAR];
        $blockName = $blockNameHierarchy[$hierarchyLevel];

        if (!isset($this->resources[$cacheKey][$blockName])) {
            $this->loadResourceForBlockNameHierarchy($cacheKey, $view, $blockNameHierarchy, $hierarchyLevel);
        }

        return $this->resources[$cacheKey][$blockName];
    }

    /**
     * {@inheritdoc}
     */
    public function getResourceHierarchyLevel(FormView $view, array $blockNameHierarchy, $hierarchyLevel)
    {
        $cacheKey = $view->vars[self::CACHE_KEY_VAR];
        $blockName = $blockNameHierarchy[$hierarchyLevel];

        if (!isset($this->resources[$cacheKey][$blockName])) {
            $this->loadResourceForBlockNameHierarchy($cacheKey, $view, $blockNameHierarchy, $hierarchyLevel);
        }

        // If $block was previously rendered loaded with loadTemplateForBlock(), the template
        // is cached but the hierarchy level is not. In this case, we know that the  block
        // exists at this very hierarchy level, so we can just set it.
        if (!isset($this->resourceHierarchyLevels[$cacheKey][$blockName])) {
            $this->resourceHierarchyLevels[$cacheKey][$blockName] = $hierarchyLevel;
        }

        return $this->resourceHierarchyLevels[$cacheKey][$blockName];
    }

    /**
     * Loads the cache with the resource for a given block name.
     *
     * @see getResourceForBlock()
     *
     * @param string   $cacheKey  The cache key of the form view
     * @param FormView $view      The form view for finding the applying themes
     * @param string   $blockName The name of the block to load
     *
     * @return bool True if the resource could be loaded, false otherwise
     */
    abstract protected function loadResourceForBlockName($cacheKey, FormView $view, $blockName);

    /**
     * Loads the cache with the resource for a specific level of a block hierarchy.
     *
     * @see getResourceForBlockHierarchy()
     */
    private function loadResourceForBlockNameHierarchy(string $cacheKey, FormView $view, array $blockNameHierarchy, int $hierarchyLevel): bool
    {
        $blockName = $blockNameHierarchy[$hierarchyLevel];

        // Try to find a template for that block
        if ($this->loadResourceForBlockName($cacheKey, $view, $blockName)) {
            // If loadTemplateForBlock() returns true, it was able to populate the
            // cache. The only missing thing is to set the hierarchy level at which
            // the template was found.
            $this->resourceHierarchyLevels[$cacheKey][$blockName] = $hierarchyLevel;

            return true;
        }

        if ($hierarchyLevel > 0) {
            $parentLevel = $hierarchyLevel - 1;
            $parentBlockName = $blockNameHierarchy[$parentLevel];

            // The next two if statements contain slightly duplicated code. This is by intention
            // and tries to avoid execution of unnecessary checks in order to increase performance.

            if (isset($this->resources[$cacheKey][$parentBlockName])) {
                // It may happen that the parent block is already loaded, but its level is not.
                // In this case, the parent block must have been loaded by loadResourceForBlock(),
                // which does not check the hierarchy of the block. Subsequently the block must have
                // been found directly on the parent level.
                if (!isset($this->resourceHierarchyLevels[$cacheKey][$parentBlockName])) {
                    $this->resourceHierarchyLevels[$cacheKey][$parentBlockName] = $parentLevel;
                }

                // Cache the shortcuts for further accesses
                $this->resources[$cacheKey][$blockName] = $this->resources[$cacheKey][$parentBlockName];
                $this->resourceHierarchyLevels[$cacheKey][$blockName] = $this->resourceHierarchyLevels[$cacheKey][$parentBlockName];

                return true;
            }

            if ($this->loadResourceForBlockNameHierarchy($cacheKey, $view, $blockNameHierarchy, $parentLevel)) {
                // Cache the shortcuts for further accesses
                $this->resources[$cacheKey][$blockName] = $this->resources[$cacheKey][$parentBlockName];
                $this->resourceHierarchyLevels[$cacheKey][$blockName] = $this->resourceHierarchyLevels[$cacheKey][$parentBlockName];

                return true;
            }
        }

        // Cache the result for further accesses
        $this->resources[$cacheKey][$blockName] = false;
        $this->resourceHierarchyLevels[$cacheKey][$blockName] = false;

        return false;
    }
}
