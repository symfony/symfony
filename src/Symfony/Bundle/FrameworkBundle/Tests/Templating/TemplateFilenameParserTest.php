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

use Symfony\Bundle\FrameworkBundle\Templating\TemplateFilenameParser;
use Symfony\Bundle\FrameworkBundle\Templating\TemplateReference;
use Symfony\Bundle\FrameworkBundle\Tests\TestCase;

class TemplateFilenameParserTest extends TestCase
{
    protected $parser;

    /**
     * @before
     */
    protected function before(): void
    {
        $this->parser = new TemplateFilenameParser();
    }

    /**
     * @after
     */
    protected function after(): void
    {
        $this->parser = null;
    }

    /**
     * @dataProvider getFilenameToTemplateProvider
     */
    public function testParseFromFilename($file, $ref)
    {
        $template = $this->parser->parse($file);

        if (false === $ref) {
            $this->assertFalse($template);
        } else {
            $this->assertEquals($template->getLogicalName(), $ref->getLogicalName());
        }
    }

    public function getFilenameToTemplateProvider()
    {
        return [
            ['/path/to/section/name.format.engine', new TemplateReference('', '/path/to/section', 'name', 'format', 'engine')],
            ['\\path\\to\\section\\name.format.engine', new TemplateReference('', '/path/to/section', 'name', 'format', 'engine')],
            ['name.format.engine', new TemplateReference('', '', 'name', 'format', 'engine')],
            ['name.format', false],
            ['name', false],
        ];
    }
}
