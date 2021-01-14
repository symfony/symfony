<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Serializer\Normalizer\Chooser;

use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

interface NormalizerChooserInterface
{
    public function chooseNormalizer(array $normalizers, $data, ?string $format = null, array $context = []): ?NormalizerInterface;

    public function chooseDenormalizer(array $denormalizers, $data, string $class, ?string $format = null, array $context = []): ?DenormalizerInterface;
}
