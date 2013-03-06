<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Templating\Tests\Helper;
use Symfony\Component\Templating\Asset\PathPackage;

class PathPackageTest extends \PHPUnit_Framework_TestCase
{

  public function testApplyRegex()
  {
    $helper = new PathPackage();

    // Making private method accessible
    $method = new \ReflectionMethod($helper, 'applyRegex');
    $method->setAccessible(TRUE);

    $emulatedGlob = function($p) { return array("foo/jquery-1.8.0.js", "foo/jquery-ui-1.8.23.js"); };
    $emptyGlob = function($p) { return array(); };

    $this->assertEquals('foo/jquery.js',
        $method->invoke($helper, 'foo/jquery.js', $emulatedGlob),
        '->applyRegex() does nothing when there is no *');
    $this->assertEquals('foo/jquery-1.8.0.js',
        $method->invoke($helper, 'foo/jquery*.js', $emulatedGlob),
        '->applyRegex() should work with simple regex with *');
    $this->assertEquals('foo/jquery**.js',
        $method->invoke($helper, 'foo/jquery**.js', $emulatedGlob),
        '->applyRegex() shouldn\'t work with regexps with more than one *');
    $this->assertEquals('foo/jquery*.js',
        $method->invoke($helper, 'foo/jquery*.js', $emptyGlob),
        '->applyRegex() shouldn\'t change path if no files have been found');
  }
}

