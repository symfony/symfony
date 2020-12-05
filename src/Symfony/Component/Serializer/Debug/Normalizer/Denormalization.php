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

use Symfony\Component\Serializer\Debug\Deserialization;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;

/**
 * Class Denormalization
 */
final class Denormalization extends Deserialization
{
    /**
     * @var DenormalizerInterface
     */
    public $denormalizer;

    public function __construct(
        DenormalizerInterface $denormalizer,
        $data,
        string $type,
        string $format,
        array $context = []
    ) {
        parent::__construct($data, $type, $format, $context);
        $this->denormalizer = $denormalizer;
    }
}
