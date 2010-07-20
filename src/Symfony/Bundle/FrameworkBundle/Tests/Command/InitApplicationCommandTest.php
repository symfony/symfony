<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests\Command;

use Symfony\Bundle\FrameworkBundle\Tests\TestCase;
use Symfony\Bundle\FrameworkBundle\Command\InitApplicationCommand;
use Symfony\Bundle\FrameworkBundle\Util\Filesystem;
use Symfony\Components\Console\Tester\CommandTester;
use Symfony\Components\HttpFoundation\Request;

require_once __DIR__.'/../TestCase.php';

class InitApplicationCommandTest extends TestCase
{
    /**
     * @dataProvider getFormat
     * @runInSeparateProcess
     */
    public function testExecution($format)
    {
        $tmpDir = sys_get_temp_dir().'/sf_hello';
        $filesystem = new Filesystem();
        $filesystem->remove($tmpDir);

        $tester = new CommandTester(new InitApplicationCommand());
        $tester->execute(array(
            'name'     => 'Hello'.$format,
            'path'     => $tmpDir.'/hello'.$format,
            'web_path' => $tmpDir.'/web',
            '--format' => $format,
        ));
        $filesystem->mkdirs($tmpDir.'/src');
        $filesystem->touch($tmpDir.'/src/autoload.php');

        $class = 'Hello'.$format.'Kernel';
        $file = $tmpDir.'/hello'.$format.'/'.$class.'.php';
        $this->assertTrue(file_exists($file));

        $content = file_get_contents($file);
        $content = str_replace(
            "__DIR__.'/../src/vendor/Symfony/src/Symfony/Bundle'",
            "'".__DIR__."/../../..'",
            $content
        );
        file_put_contents($file, $content);

        require_once $file;

        $kernel = new $class('dev', true);
        $response = $kernel->handle(Request::create('/'));

        $this->assertRegExp('/successfully/', $response->getContent());

        $filesystem->remove($tmpDir);
    }

    public function getFormat()
    {
        return array(
            array('xml'),
            array('yml'),
            array('php'),
        );
    }

    protected function prepareTemplate(\Text_Template $template)
    {
        $template->setFile(__DIR__.'/TestCaseMethod.tpl');
    }
}
