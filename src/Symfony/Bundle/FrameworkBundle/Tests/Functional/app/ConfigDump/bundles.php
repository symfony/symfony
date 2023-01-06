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
use Symfony\Bundle\FrameworkBundle\Tests\Functional\Bundle\DefaultConfigTestBundle\DefaultConfigTestBundle;
use Symfony\Bundle\FrameworkBundle\Tests\Functional\Bundle\ExtensionWithoutConfigTestBundle\ExtensionWithoutConfigTestBundle;
use Symfony\Bundle\FrameworkBundle\Tests\Functional\Bundle\TestBundle\TestBundle;

return [
    new DefaultConfigTestBundle(),
    new ExtensionWithoutConfigTestBundle(),
    new FrameworkBundle(),
    new TestBundle(),
];
