<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Loader;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Config\Loader\FileLoader as BaseFileLoader;
use Symfony\Component\Config\FileLocatorInterface;

/**
 * FileLoader is the abstract class used by all built-in loaders that are file based.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
abstract class FileLoader extends BaseFileLoader
{
    protected $container;

    /**
     * @param ContainerBuilder     $container A ContainerBuilder instance
     * @param FileLocatorInterface $locator   A FileLocator instance
     */
    public function __construct(ContainerBuilder $container, FileLocatorInterface $locator)
    {
        $this->container = $container;

        parent::__construct($locator);
    }

    /**
     * Generates the ID of an "instanceof" definition.
     *
     * @param string $id
     * @param string $type
     * @param string $file
     *
     * @return string
     */
    protected function generateInstanceofDefinitionId($id, $type, $file)
    {
        return sprintf("%s_%s_%s", $id, $type, hash('sha256', $file));
    }
}
