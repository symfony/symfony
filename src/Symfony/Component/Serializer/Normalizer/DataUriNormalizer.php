<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Serializer\Normalizer;

use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\File\MimeType\MimeTypeGuesser;
use Symfony\Component\HttpFoundation\File\MimeType\MimeTypeGuesserInterface;
use Symfony\Component\Serializer\Exception\UnexpectedValueException;

/**
 * Normalizes a {@see \SplFileInfo} object to a data URI.
 * Denormalizes a data URI to a {@see \SplFileObject} object.
 *
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
class DataUriNormalizer implements NormalizerInterface, DenormalizerInterface
{
    /**
     * @var MimeTypeGuesserInterface
     */
    private $mimeTypeGuesser;

    public function __construct(MimeTypeGuesserInterface $mimeTypeGuesser = null)
    {
        if (null === $mimeTypeGuesser && class_exists('Symfony\Component\HttpFoundation\File\MimeType\MimeTypeGuesser')) {
            $mimeTypeGuesser = MimeTypeGuesser::getInstance();
        }

        $this->mimeTypeGuesser = $mimeTypeGuesser;
    }

    /**
     * {@inheritdoc}
     */
    public function normalize($object, $format = null, array $context = array())
    {
        if ($object instanceof File) {
            $mimeType = $object->getMimeType();
        } elseif ($this->mimeTypeGuesser) {
            $mimeType = $this->mimeTypeGuesser->guess($object->getPathname());
        } else {
            $mimeType = 'application/octet-stream';
        }

        list($typeName) = explode('/', $mimeType, 2);

        if (!$object instanceof \SplFileObject) {
            $object = $object->openFile();
        }

        $data = '';

        $object->rewind();
        while (!$object->eof()) {
            $data .= $object->fgets();
        }

        if ('text' === $typeName) {
            return sprintf('data:%s,%s', $mimeType, rawurlencode($data));
        }

        return sprintf('data:%s;base64,%s', $mimeType, base64_encode($data));
    }

    /**
     * {@inheritdoc}
     */
    public function supportsNormalization($data, $format = null)
    {
        return $data instanceof \SplFileInfo;
    }

    /**
     * {@inheritdoc}
     *
     * Regex adapted from Brian Grinstead code.
     *
     * @see https://gist.github.com/bgrins/6194623
     *
     * @throws UnexpectedValueException
     */
    public function denormalize($data, $class, $format = null, array $context = array())
    {
        if (!preg_match('/^data:([a-z0-9]+\/[a-z0-9]+(;[a-z0-9\-]+\=[a-z0-9\-]+)?)?(;base64)?,[a-z0-9\!\$\&\\\'\,\(\)\*\+\,\;\=\-\.\_\~\:\@\/\?\%\s]*\s*$/i', $data)) {
            throw new UnexpectedValueException('The provided "data:" URI is not valid.');
        }

        try {
            if ('Symfony\Component\HttpFoundation\File\File' === $class) {
                return new File($data, false);
            }

            return new \SplFileObject($data);
        } catch (\RuntimeException $exception) {
            throw new UnexpectedValueException($exception->getMessage(), $exception->getCode(), $exception);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function supportsDenormalization($data, $type, $format = null)
    {
        $supportedTypes = array(
            'SplFileInfo' => true,
            'SplFileObject' => true,
            'Symfony\Component\HttpFoundation\File\File' => true,
        );

        return isset($supportedTypes[$type]);
    }
}
