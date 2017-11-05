<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Intl\Data\Bundle\Compiler;

use Symfony\Component\Intl\Exception\RuntimeException;

/**
 * Compiles .txt resource bundles to binary .res files.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 *
 * @internal
 */
class GenrbCompiler implements BundleCompilerInterface
{
    private $genrb;

    /**
     * Creates a new compiler based on the "genrb" executable.
     *
     * @param string $genrb   Optional. The path to the "genrb" executable
     * @param string $envVars Optional. Environment variables to be loaded when running "genrb".
     *
     * @throws RuntimeException if the "genrb" cannot be found
     */
    public function __construct($genrb = 'genrb', $envVars = '')
    {
        exec('which '.$genrb, $output, $status);

        if (0 !== $status) {
            throw new RuntimeException(sprintf(
                'The command "%s" is not installed',
                $genrb
            ));
        }

        $this->genrb = ($envVars ? $envVars.' ' : '').$genrb;
    }

    /**
     * {@inheritdoc}
     */
    public function compile($sourcePath, $targetDir)
    {
        if (is_dir($sourcePath)) {
            $sourcePath .= '/*.txt';
        }

        exec($this->genrb.' --quiet -e UTF-8 -d '.$targetDir.' '.$sourcePath, $output, $status);

        if (0 !== $status) {
            throw new RuntimeException(sprintf(
                'genrb failed with status %d while compiling %s to %s.',
                $status,
                $sourcePath,
                $targetDir
            ));
        }
    }
}
