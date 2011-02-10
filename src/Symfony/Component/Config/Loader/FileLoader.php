<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Config\Loader;

/**
 * FileLoader is the abstract class used by all built-in loaders that are file based.
 *
 * @author Fabien Potencier <fabien.potencier@symfony-project.com>
 */
abstract class FileLoader extends Loader
{
    protected $locator;
    protected $currentDir;

    /**
     * Constructor.
     */
    public function __construct(FileLocator $locator)
    {
        $this->locator = $locator;
    }

    public function getLocator()
    {
        return $this->locator;
    }

    /**
     * Adds definitions and parameters from a resource.
     *
     * @param mixed $resource A Resource
     */
    public function import($resource, $ignoreErrors = false)
    {
        try {
            $loader = $this->resolve($resource);

            if ($loader instanceof FileLoader && null !== $this->currentDir) {
                $resource = $this->locator->getAbsolutePath($resource, $this->currentDir);
            }

            $loader->load($resource);
        } catch (\Exception $e) {
            if (!$ignoreErrors) {
                throw $e;
            }
        }
    }
}
