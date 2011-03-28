<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Component\Routing\Generator\Dumper\PhpGeneratorDumper;

use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\Generator\Dumper\PhpGeneratorDumper;

class PhpGeneratorDumperTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var RouteCollection
     */
    private $routeCollection;
    
    /**
     * @var PhpGeneratorDumper
     */
    private $generatorDumper;
    
    /**
     * @var string
     */
    private $testTmpFilepath;

    protected function setUp()
    {
        parent::setUp();

        $this->routeCollection = new RouteCollection();
        $this->generatorDumper = new PhpGeneratorDumper($this->routeCollection);
        $this->testTmpFilepath = sys_get_temp_dir().DIRECTORY_SEPARATOR.'php_generator.php';
        @unlink($this->testTmpFilepath);
    }

    protected function tearDown()
    {
        parent::tearDown();
        
        @unlink($this->testTmpFilepath);
    }

    public function testDumpWithRoutes()
    {
        $this->routeCollection->add('Test', new Route('/testing/{foo}'));
        $this->routeCollection->add('Test2', new Route('/testing2'));
        
        file_put_contents($this->testTmpFilepath, $this->generatorDumper->dump());
        include ($this->testTmpFilepath);

        $projectUrlGenerator = new \ProjectUrlGenerator(array(
            'base_url' => '/app.php',
            'method' => 'GET',
            'host' => 'localhost',
            'port' => 80,
            'is_secure' => false
        ));
        
        $absoluteUrlWithParameter    = $projectUrlGenerator->generate('Test', array('foo' => 'bar'), true);
        $absoluteUrlWithoutParameter = $projectUrlGenerator->generate('Test2', array(), true);
        $relativeUrlWithParameter    = $projectUrlGenerator->generate('Test', array('foo' => 'bar'), false);
        $relativeUrlWithoutParameter = $projectUrlGenerator->generate('Test2', array(), false);

        $this->assertEquals($absoluteUrlWithParameter, 'http://localhost/app.php/testing/bar');
        $this->assertEquals($absoluteUrlWithoutParameter, 'http://localhost/app.php/testing2');
        $this->assertEquals($relativeUrlWithParameter, '/app.php/testing/bar');
        $this->assertEquals($relativeUrlWithoutParameter, '/app.php/testing2');
    }
    
    /**
     * @expectedException \InvalidArgumentException
     */
    public function testDumpWithoutRoutes()
    {
        file_put_contents($this->testTmpFilepath, $this->generatorDumper->dump(array('class' => 'WithoutRoutesUrlGenerator')));
        include ($this->testTmpFilepath);

        $projectUrlGenerator = new \WithoutRoutesUrlGenerator(array(
            'base_url' => '/app.php',
            'method' => 'GET',
            'host' => 'localhost',
            'port' => 80,
            'is_secure' => false
        ));
       
        $projectUrlGenerator->generate('Test', array());
    }
    
    public function testDumpForRouteWithDefaults()
    {
        $this->routeCollection->add('Test', new Route('/testing/{foo}', array('foo' => 'bar')));
        
        file_put_contents($this->testTmpFilepath, $this->generatorDumper->dump(array('class' => 'DefaultRoutesUrlGenerator')));
        include ($this->testTmpFilepath);
        
        $projectUrlGenerator = new \DefaultRoutesUrlGenerator(array());
        $url = $projectUrlGenerator->generate('Test', array());

        $this->assertEquals($url, '/testing');
    }
}
