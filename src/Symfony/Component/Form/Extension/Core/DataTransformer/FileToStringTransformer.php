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
class FileToStringTransformer implements DataTransformerInterface
{
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

    public function reverseTransform($path)
    {
        if (null === $path || '' === $path) {
            return null;
        }

        if (!is_string($path)) {
            throw new UnexpectedTypeException($path, 'string');
        }

        return new File($path);
    }
}