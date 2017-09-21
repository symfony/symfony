<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

require __DIR__.'/../src/Bar.php';
require __DIR__.'/../src/Foo.php';

require __DIR__.'/../../../Legacy/CoverageListenerTrait.php';
if (class_exists('PHPUnit_Runner_Version') && version_compare(\PHPUnit_Runner_Version::id(), '6.0.0', '<')) {
    require __DIR__.'/../../../Legacy/CoverageListener.php';
}
require __DIR__.'/../../../CoverageListener.php';
