<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\PropertyAccess\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\PropertyAccess\StringUtil;

/**
 * @group legacy
 */
class StringUtilTest extends TestCase
{
    public function singularifyProvider()
    {
        // This is only a stub to make sure the BC layer works
        // Actual tests are in the Symfony Inflector component
        return array(
            array('axes', array('ax', 'axe', 'axis')),
        );
    }

    /**
     * @dataProvider singularifyProvider
     */
    public function testSingularify($plural, $singular)
    {
        $single = StringUtil::singularify($plural);
        if (is_string($singular) && is_array($single)) {
            $this->fail("--- Expected\n`string`: ".$singular."\n+++ Actual\n`array`: ".implode(', ', $single));
        } elseif (is_array($singular) && is_string($single)) {
            $this->fail("--- Expected\n`array`: ".implode(', ', $singular)."\n+++ Actual\n`string`: ".$single);
        }

        $this->assertEquals($singular, $single);
    }
}
