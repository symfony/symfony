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
    static protected $fixturesPath;

    static public function setUpBeforeClass()
    {
        self::$fixturesPath = realpath(__DIR__.'/../../Fixtures/');
    }
    
    protected function setUp()
    {
        parent::setUp();

        $this->routeCollection = new RouteCollection();
        $this->generatorDumper = new PhpGeneratorDumper($this->routeCollection);
    }

    public function testDumpWithRoutes()
    {
        $this->routeCollection->add('Test', new Route('/testing'));
        
        $this->assertStringEqualsFile(self::$fixturesPath.'/dumper/php_generator1.php', $this->generatorDumper->dump()); 
    }
    
    public function testDumpWithoutRoutes()
    {
        $this->assertStringEqualsFile(self::$fixturesPath.'/dumper/php_generator2.php', $this->generatorDumper->dump()); 
    }
    
    public function testDumpWithClassNamesOptions()
    {
        $this->routeCollection->add('Test', new Route('/testing'));
        
        $this->assertStringEqualsFile(self::$fixturesPath.'/dumper/php_generator3.php', $this->generatorDumper->dump(array('class' => 'FooGenerator', 'base_class' => 'FooGeneratorBase')));
    }

    public function testDumpForRouteWithDefaults()
    {
        $this->routeCollection->add('Test', new Route('/testing/{foo}', array('foo' => 'bar')));
        
        $this->assertStringEqualsFile(self::$fixturesPath.'/dumper/php_generator4.php', $this->generatorDumper->dump());
    }
}
