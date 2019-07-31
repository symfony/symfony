<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Translation\Tests\Loader;

use PHPUnit\Framework\TestCase;
use Symfony\Bridge\PhpUnit\ForwardCompatTestTrait;

abstract class LocalizedTestCase extends TestCase
{
    use ForwardCompatTestTrait;

    private function doSetUp()
    {
        if (!\extension_loaded('intl')) {
            $this->markTestSkipped('Extension intl is required.');
        }
    }
}
