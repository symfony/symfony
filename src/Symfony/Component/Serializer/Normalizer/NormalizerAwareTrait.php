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

/**
 * @author Joel Wurtz <joel.wurtz@gmail.com>
 */
trait NormalizerAwareTrait
{
    /**
     * @var NormalizerInterface
     */
    protected $normalizer;

    public function setNormalizer(NormalizerInterface $normalizer)
    {
        $this->normalizer = $normalizer;
    }
}
