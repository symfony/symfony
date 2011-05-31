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
use Symfony\Component\Form\Exception\TransformationFailedException;
use Symfony\Component\Form\Exception\UnexpectedTypeException;
use Symfony\Component\HttpFoundation\File\File;

/**
 * @author Bernhard Schussek <bernhard.schussek@symfony-project.com>
 */
class FileToArrayTransformer implements DataTransformerInterface
{
    /**
     * Convert a File instance to a file representation
     *
     * @param File $file The file
     *
     * @return array The file representation
     *
     * @throws UnexpectedTypeException if the file is not an instance of File
     */
    public function transform($file)
    {
        if (null === $file || '' === $file) {
            return array('file' => '');
        }

        if (!$file instanceof File) {
            throw new UnexpectedTypeException($file, 'Symfony\Component\HttpFoundation\File\File');
        }

        return array('file' => $file);
    }

    /**
     * Transform a file internal representation to a File instance
     *
     * @param File $array the file representation
     *
     * @return File The file
     *
     * @throws UnexpectedTypeException if the file representation is not an array
     * @throws TransformationFailedException if the file representation is invalid
     */
    public function reverseTransform($array)
    {
        if (null === $array || '' === $array || array() === $array) {
            return null;
        }

        if (!is_array($array)) {
            throw new UnexpectedTypeException($array, 'array');
        }

        if (!array_key_exists('file', $array)) {
            throw new TransformationFailedException('The key "file" is missing');
        }

        if (!empty($array['file']) && !$array['file'] instanceof File) {
            throw new TransformationFailedException('The key "file" should be empty or instance of File');
        }

        return $array['file'];
    }
}