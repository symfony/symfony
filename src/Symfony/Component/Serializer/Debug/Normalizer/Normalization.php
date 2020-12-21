<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Serializer\Debug\Normalizer;

use Symfony\Component\Serializer\Debug\Serialization;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

final class Normalization extends Serialization
{
    public $normalizer;

    public function __construct(NormalizerInterface $normalizer, $data, $result, string $format, array $context = [])
    {
        parent::__construct($data, $result, $format, $context);
        $this->normalizer = $normalizer;
    }
}
