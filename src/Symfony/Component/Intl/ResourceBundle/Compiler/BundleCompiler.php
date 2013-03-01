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

use Symfony\Component\Intl\Exception\RuntimeException;

/**
 * Compiles .txt resource bundles to binary .res files.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class BundleCompiler implements ResourceBundleCompilerInterface
{
    /**
     * @var string The path to the "genrb" executable.
     */
    private $genrb;

    /**
     * Creates a new compiler based on the "genrb" executable.
     *
     * @param string $genrb Optional. The path to the "genrb" executable.
     *
     * @throws RuntimeException If the "genrb" cannot be found.
     */
    public function __construct($genrb = 'genrb')
    {
        exec('which ' . $genrb, $output, $status);

        if (0 !== $status) {
            throw new RuntimeException(sprintf(
                'The command "%s" is not installed',
                $genrb
            ));
        }

        $this->genrb = $genrb;
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

        if ($status !== 0) {
            throw new RuntimeException(sprintf(
                'genrb failed with status %d while compiling %s to %s.',
                $status,
                $sourcePath,
                $targetDir
            ));
        }
    }
}
