<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Intl\ResourceBundle\Transformer;

/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
interface StubbingContextInterface
{
    /**
     * Returns the directory where the binary resource bundles are stored.
     *
     * @return string An absolute path to a directory.
     */
    public function getBinaryDir();

    /**
     * Returns the directory where the stub resource bundles are stored.
     *
     * @return string An absolute path to a directory.
     */
    public function getStubDir();

    /**
     * Returns a tool for manipulating the filesystem.
     *
     * @return \Symfony\Component\Filesystem\Filesystem The filesystem manipulator.
     */
    public function getFilesystem();

    /**
     * Returns the ICU version of the bundles being converted.
     *
     * @return string The ICU version string.
     */
    public function getIcuVersion();
}
