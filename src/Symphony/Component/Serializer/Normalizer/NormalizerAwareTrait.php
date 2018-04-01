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
 * NormalizerAware trait.
 *
 * @author Joel Wurtz <joel.wurtz@gmail.com>
 */
trait NormalizerAwareTrait
{
    /**
     * @var NormalizerInterface
     */
    protected $normalizer;

    /**
     * Sets the normalizer.
     *
     * @param NormalizerInterface $normalizer A NormalizerInterface instance
     */
    public function setNormalizer(NormalizerInterface $normalizer)
    {
        $this->normalizer = $normalizer;
    }
}
