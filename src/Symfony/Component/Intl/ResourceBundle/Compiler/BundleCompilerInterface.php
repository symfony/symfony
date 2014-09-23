<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Intl\ResourceBundle\Compiler;

/**
 * Compiles a resource bundle.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 *
 * @internal
 */
interface BundleCompilerInterface
{
    /**
     * Compiles a resource bundle at the given source to the given target
     * directory.
     *
     * @param string $sourcePath
     * @param string $targetDir
     */
    public function compile($sourcePath, $targetDir);
}
