<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Component\Templating;

use Symfony\Component\Templating\TemplateNameParser;
use Symfony\Component\Templating\TemplateReference;

class TemplateNameParserTest extends \PHPUnit_Framework_TestCase
{
    protected $parser;

    protected function  setUp()
    {
        $this->parser = new TemplateNameParser();
    }

    protected function tearDown()
    {
        unset($this->parser);
    }

    /**
     * @dataProvider getLogicalNameToTemplateProvider
     */
    public function testParse($name, $ref, $refname)
    {
        $template = $this->parser->parse($name);

        $this->assertEquals($template->getSignature(), $ref->getSignature());
        $this->assertEquals($template->getName(), $refname);
    }

    public function getLogicalNameToTemplateProvider()
    {
        return array(
            array('/path/to/section/name.engine', new TemplateReference('/path/to/section/name.engine', 'engine'), '/path/to/section/name.engine'),
            array('\\path\\to\\section\\name.engine', new TemplateReference('/path/to/section/name.engine', 'engine'), '/path/to/section/name.engine'),
            array('name.engine', new TemplateReference('name.engine', 'engine'), 'name.engine'),
            array('name', new TemplateReference('name'), 'name'),
        );
    }
}