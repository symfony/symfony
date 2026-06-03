<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Tests\Functional\Bundle\LegacyBundle\LegacyBundle;
use Symfony\Bundle\FrameworkBundle\Tests\Functional\Bundle\ModernBundle\src\ModernBundle;
use Symfony\Bundle\TwigBundle\TwigBundle;

return [
    new FrameworkBundle(),
    new TwigBundle(),
    new ModernBundle(),
    new LegacyBundle(),
];
