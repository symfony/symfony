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
use Symfony\Bundle\SecurityBundle\SecurityBundle;
use Symfony\Bundle\SecurityBundle\Tests\Functional\Bundle\FormLoginBundle\FormLoginBundle;
use Symfony\Bundle\SecurityBundle\Tests\Functional\Bundle\TestBundle;
use Symfony\Bundle\TwigBundle\TwigBundle;

return [
    new FrameworkBundle(),
    new SecurityBundle(),
    new TwigBundle(),
    new FormLoginBundle(),
    new TestBundle(),
];
