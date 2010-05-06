<?php

/*
 * This file is part of the Symfony package.
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Components\Console\Formatter;

use Symfony\Components\Console\Helper\FormatterHelper;

class FormatterHelperTest extends \PHPUnit_Framework_TestCase
{
    public function testFormatSection()
    {
        $formatter = new FormatterHelper();

        $this->assertEquals('<info>[cli]</info> Some text to display', $formatter->formatSection('cli', 'Some text to display'), '::formatSection() formats a message in a section');
    }

    public function testFormatBlock()
    {
        $formatter = new FormatterHelper();

        $this->assertEquals('<error> Some text to display </error>', $formatter->formatBlock('Some text to display', 'error'), '::formatBlock() formats a message in a block');
        $this->assertEquals("<error> Some text to display </error>\n<error> foo bar              </error>", $formatter->formatBlock(array('Some text to display', 'foo bar'), 'error'), '::formatBlock() formats a message in a block');

        $this->assertEquals("<error>                        </error>\n<error>  Some text to display  </error>\n<error>                        </error>", $formatter->formatBlock('Some text to display', 'error', true), '::formatBlock() formats a message in a block');
    }
}
