<?php

/*
 * This file is part of the symfony package.
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Components\Console\Formatter;

require_once __DIR__.'/../../../bootstrap.php';

use Symfony\Components\Console\Helper\FormatterHelper;

class FormatterHelperTest extends \PHPUnit_Framework_TestCase
{
  public function testFormatSection()
  {
    $formatter = new FormatterHelper();

    $this->assertEquals($formatter->formatSection('cli', 'Some text to display'), '<info>[cli]</info> Some text to display', '::formatSection() formats a message in a section');
  }

  public function testFormatBlock()
  {
    $formatter = new FormatterHelper();

    $this->assertEquals($formatter->formatBlock('Some text to display', 'error'), '<error> Some text to display </error>', '::formatBlock() formats a message in a block');
    $this->assertEquals($formatter->formatBlock(array('Some text to display', 'foo bar'), 'error'), "<error> Some text to display </error>\n<error> foo bar              </error>", '::formatBlock() formats a message in a block');

    $this->assertEquals($formatter->formatBlock('Some text to display', 'error', true), "<error>                        </error>\n<error>  Some text to display  </error>\n<error>                        </error>", '::formatBlock() formats a message in a block');
  }
}
