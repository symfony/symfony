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

class DifferentMethodFeature
{
    #[AsFeature(method: 'different')]
    public function resolve(): bool
    {
        return true;
    }
}
