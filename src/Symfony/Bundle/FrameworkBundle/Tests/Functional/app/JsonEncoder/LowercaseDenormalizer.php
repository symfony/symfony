<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests\Functional\app\JsonEncoder;

use Symfony\Component\JsonEncoder\Decode\Denormalizer\DenormalizerInterface;
use Symfony\Component\TypeInfo\Type;
use Symfony\Component\TypeInfo\Type\BuiltinType;

/**
 * @author Mathias Arlaud <mathias.arlaud@gmail.com>
 */
class LowercaseDenormalizer implements DenormalizerInterface
{
    public function denormalize(mixed $normalized, array $options = []): string
    {
        return strtolower($normalized);
    }

    public static function getNormalizedType(): BuiltinType
    {
        return Type::string();
    }
}
