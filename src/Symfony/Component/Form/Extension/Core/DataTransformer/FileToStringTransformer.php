<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Extension\Core\DataTransformer;

use Symfony\Component\Form\DataTransformerInterface;
use Symfony\Component\Form\Exception\UnexpectedTypeException;
use Symfony\Component\HttpFoundation\File\Exception\FileNotFoundException;
use Symfony\Component\Form\Exception\TransformationFailedException;
use Symfony\Component\HttpFoundation\File\File;

/**
 * @author Bernhard Schussek <bernhard.schussek@symfony-project.com>
 */
class FileToStringTransformer implements DataTransformerInterface
{
    /**
     * Transforms a File instance to a path
     *
     * @param File $file The file
     *
     * @return string The path to the file
     *
     * @throws UnexpectedTypeException if the given file is not an instance of File
     */
    public function transform($file)
    {
        if (null === $file || '' === $file) {
            return '';
        }

        if (!$file instanceof File) {
            throw new UnexpectedTypeException($file, 'Symfony\Component\HttpFoundation\File\File');
        }

        return $file->getPath();
    }


    /**
     * Transforms a path to a File instance
     *
     * @param string $path The path to the file
     *
     * @return File The File
     *
     * @throws UnexpectedTypeException if the given path is not a string
     * @throws TransformationFailedException if the File instance could not be created
     */
    public function reverseTransform($path)
    {
        if (null === $path || '' === $path) {
            return null;
        }

        if (!is_string($path)) {
            throw new UnexpectedTypeException($path, 'string');
        }

        try {
            $file = new File($path);
        } catch (FileNotFoundException $e) {
            throw new TransformationFailedException(
                sprintf('The file "%s" does not exist', $path),
                $e->getCode(),
                $e
            );
        }

        return $file;
    }
}