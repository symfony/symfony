<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Templating\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Templating\TemplateNameParser;
use Symfony\Component\Templating\TemplateReference;

class TemplateNameParserTest extends TestCase
{
    protected $parser;

    protected function setUp(): void
    {
        $this->parser = new TemplateNameParser();
    }

    protected function tearDown(): void
    {
        $this->parser = null;
    }

    /**
     * @dataProvider getLogicalNameToTemplateProvider
     */
    public function testParse($name, $ref)
    {
        $template = $this->parser->parse($name);

        $this->assertEquals($template->getLogicalName(), $ref->getLogicalName());
        $this->assertEquals($template->getLogicalName(), $name);
    }

    public static function getLogicalNameToTemplateProvider()
    {
        return [
            ['/path/to/section/name.engine', new TemplateReference('/path/to/section/name.engine', 'engine')],
            ['name.engine', new TemplateReference('name.engine', 'engine')],
            ['name', new TemplateReference('name')],
        ];
    }
}
