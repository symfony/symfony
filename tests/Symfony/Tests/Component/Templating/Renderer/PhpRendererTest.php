<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Component\Templating\Renderer;

use Symfony\Component\Templating\Renderer\PhpRenderer;
use Symfony\Component\Templating\Storage\Storage;
use Symfony\Component\Templating\Storage\StringStorage;
use Symfony\Component\Templating\Storage\FileStorage;

class PhpRendererTest extends \PHPUnit_Framework_TestCase
{
    public function testEvaluate()
    {
        $renderer = new PhpRenderer();

        $template = new StringStorage('<?php echo $foo ?>');
        $this->assertEquals('bar', $renderer->evaluate($template, array('foo' => 'bar')), '->evaluate() renders templates that are instances of StringStorage');

        $template = new FileStorage(__DIR__.'/../Fixtures/templates/foo.php');
        $this->assertEquals('bar', $renderer->evaluate($template, array('foo' => 'bar')), '->evaluate() renders templates that are instances of FileStorage');
    }
}
