<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests\Fixtures\FeatureFlag;

use Symfony\Component\FeatureFlag\Attribute\AsFeature;

class MethodFeature
{
    #[AsFeature(name: 'method_string')]
    public function string(): string
    {
        return 'green';
    }

    #[AsFeature(name: 'method_int')]
    public function int(): int
    {
        return 42;
    }
}
