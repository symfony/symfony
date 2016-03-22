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

use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouteContext;
use Symfony\Component\Serializer\Exception\InvalidArgumentException;

/**
 * @author Kevin Bond <kevinbond@gmail.com>
 */
class RouteContextNormalizer implements NormalizerInterface
{
    private $urlGenerator;

    public function __construct(UrlGeneratorInterface $urlGenerator)
    {
        $this->urlGenerator = $urlGenerator;
    }

    /**
     * {@inheritdoc}
     */
    public function normalize($object, $format = null, array $context = array())
    {
        if (!$object instanceof RouteContext) {
            throw new InvalidArgumentException('The object must be an instance of "Symfony\Component\Routing\RouteContext".');
        }

        return $this->urlGenerator->generate($object->getName(), $object->getParameters(), $object->getReferenceType());
    }

    /**
     * {@inheritdoc}
     */
    public function supportsNormalization($data, $format = null)
    {
        return $data instanceof RouteContext;
    }
}
