<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests\Templating;

use Symfony\Bundle\FrameworkBundle\Tests\TestCase;
use Symfony\Bundle\FrameworkBundle\Templating\TemplateFilenameParser;
use Symfony\Bundle\FrameworkBundle\Templating\TemplateReference;

class TemplateFilenameParserTest extends TestCase
{
    protected $parser;

    protected function setUp()
    {
        $this->parser = new TemplateFilenameParser();
    }

    protected function tearDown()
    {
        $this->parser = null;
    }

    /**
     * @dataProvider getFilenameToTemplateProvider
     */
    public function testParseFromFilename($file, $ref)
    {
        $template = $this->parser->parse($file);

        if ($ref === false) {
            $this->assertFalse($template);
        } else {
            $this->assertEquals($template->getLogicalName(), $ref->getLogicalName());
        }
    }

    public function getFilenameToTemplateProvider()
    {
        return array(
            array('/path/to/section/name.format.engine', new TemplateReference('', '/path/to/section', 'name', 'format', 'engine')),
            array('\\path\\to\\section\\name.format.engine', new TemplateReference('', '/path/to/section', 'name', 'format', 'engine')),
            array('name.format.engine', new TemplateReference('', '', 'name', 'format', 'engine')),
            array('name.format', false),
            array('name', false),
        );
    }
}
