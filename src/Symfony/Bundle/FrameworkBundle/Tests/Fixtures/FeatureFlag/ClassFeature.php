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

#[AsFeature]
class ClassFeature
{
    public function __invoke(): bool
    {
        return true;
    }
}
