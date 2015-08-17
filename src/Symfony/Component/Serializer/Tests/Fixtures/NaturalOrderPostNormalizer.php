<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Serializer\Tests\Fixtures;

use Symfony\Component\Serializer\Normalizer\PostNormalizerInterface;

/**
 * @author Maxime Steinhausser <maxime.steinhausser@gmail.com>
 */
class NaturalOrderPostNormalizer implements PostNormalizerInterface
{
    /**
     * {@inheritdoc}
     */
    public function postNormalize($originalData, $data, $format = null, array $context = array())
    {
        uksort($data, 'strnatcasecmp');

        return $data;
    }

    /**
     * {@inheritdoc}
     */
    public function supportsPostNormalization($originalData, $data, $format = null)
    {
        return is_array($data) && !ctype_digit(implode('', array_keys($data)));
    }
}
