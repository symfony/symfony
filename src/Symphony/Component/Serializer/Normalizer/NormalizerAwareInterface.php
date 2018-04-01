<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\Serializer\Normalizer;

/**
 * Class accepting a normalizer.
 *
 * @author Joel Wurtz <joel.wurtz@gmail.com>
 */
interface NormalizerAwareInterface
{
    /**
     * Sets the owning Normalizer object.
     *
     * @param NormalizerInterface $normalizer
     */
    public function setNormalizer(NormalizerInterface $normalizer);
}
