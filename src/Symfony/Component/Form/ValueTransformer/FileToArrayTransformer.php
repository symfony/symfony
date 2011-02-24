<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\ValueTransformer;

use Symfony\Component\Form\ValueTransformer\TransformationFailedException;
use Symfony\Component\Form\Exception\UnexpectedTypeException;
use Symfony\Component\HttpFoundation\File\File;

/**
 * @author Bernhard Schussek <bernhard.schussek@symfony-project.com>
 */
class FileToArrayTransformer implements ValueTransformerInterface
{
    public function transform($file)
    {
        if (null === $file || '' === $file) {
            return array(
                'file' => '',
                'token' => '',
                'name' => '',
            );
        }

        if (!$file instanceof File) {
            throw new UnexpectedTypeException($file, 'Symfony\Component\HttpFoundation\File\File');
        }

        return array(
            'file' => $file,
            'token' => '',
            'name' => '',
        );
    }

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