<?php

/*
 * This file is part of the symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Framework\WebBundle\Util;

use Symfony\Framework\WebBundle\Util\Mustache;

class MustacheTest extends \PHPUnit_Framework_TestCase
{
  public function testRenderString()
  {
    $template = 'Hi {{ you }}, my name is {{ me }}!';
    $expected = 'Hi {{ you }}, my name is Kris!';

    $this->assertEquals(Mustache::renderString($template, array('me' => 'Kris')), $expected, '::renderString() does not modify unknown parameters');
  }
}
