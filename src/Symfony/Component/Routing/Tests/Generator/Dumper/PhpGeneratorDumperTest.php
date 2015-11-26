<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Routing\Tests\Generator\Dumper;

use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\Generator\Dumper\PhpGeneratorDumper;
use Symfony\Component\Routing\RequestContext;

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

    /**
     * @var string
     */
    private $largeTestTmpFilepath;

    protected function setUp()
    {
        parent::setUp();

        $this->routeCollection = new RouteCollection();
        $this->generatorDumper = new PhpGeneratorDumper($this->routeCollection);
        $this->testTmpFilepath = sys_get_temp_dir().DIRECTORY_SEPARATOR.'php_generator.'.$this->getName().'.php';
        $this->largeTestTmpFilepath = sys_get_temp_dir().DIRECTORY_SEPARATOR.'php_generator.'.$this->getName().'.large.php';
        @unlink($this->testTmpFilepath);
        @unlink($this->largeTestTmpFilepath);
    }

    protected function tearDown()
    {
        parent::tearDown();

        @unlink($this->testTmpFilepath);

        $this->routeCollection = null;
        $this->generatorDumper = null;
        $this->testTmpFilepath = null;
    }

    public function testDumpWithRoutes()
    {
        $this->routeCollection->add('Test', new Route('/testing/{foo}'));
        $this->routeCollection->add('Test2', new Route('/testing2'));

        file_put_contents($this->testTmpFilepath, $this->generatorDumper->dump());
        include $this->testTmpFilepath;

        $projectUrlGenerator = new \ProjectUrlGenerator(new RequestContext('/app.php'));

        $absoluteUrlWithParameter = $projectUrlGenerator->generate('Test', array('foo' => 'bar'), UrlGeneratorInterface::ABSOLUTE_URL);
        $absoluteUrlWithoutParameter = $projectUrlGenerator->generate('Test2', array(), UrlGeneratorInterface::ABSOLUTE_URL);
        $relativeUrlWithParameter = $projectUrlGenerator->generate('Test', array('foo' => 'bar'), UrlGeneratorInterface::ABSOLUTE_PATH);
        $relativeUrlWithoutParameter = $projectUrlGenerator->generate('Test2', array(), UrlGeneratorInterface::ABSOLUTE_PATH);

        $this->assertEquals($absoluteUrlWithParameter, 'http://localhost/app.php/testing/bar');
        $this->assertEquals($absoluteUrlWithoutParameter, 'http://localhost/app.php/testing2');
        $this->assertEquals($relativeUrlWithParameter, '/app.php/testing/bar');
        $this->assertEquals($relativeUrlWithoutParameter, '/app.php/testing2');
    }

    public function testDumpWithTooManyRoutes()
    {
        $this->routeCollection->add('Test', new Route('/testing/{foo}'));
        for ( $i = 0; $i < 32769; ++$i ) {
            $this->routeCollection->add('route_'.$i, new Route('/route_'.$i));
        }
        $this->routeCollection->add('Test2', new Route('/testing2'));

        $data = $this->generatorDumper->dump(array(
            'class' => 'ProjectLargeUrlGenerator',
        ));
        file_put_contents($this->largeTestTmpFilepath, $data);
        include $this->largeTestTmpFilepath;

        $projectUrlGenerator = new \ProjectLargeUrlGenerator(new RequestContext('/app.php'));

        $absoluteUrlWithParameter = $projectUrlGenerator->generate('Test', array('foo' => 'bar'), UrlGeneratorInterface::ABSOLUTE_URL);
        $absoluteUrlWithoutParameter = $projectUrlGenerator->generate('Test2', array(), UrlGeneratorInterface::ABSOLUTE_URL);
        $relativeUrlWithParameter = $projectUrlGenerator->generate('Test', array('foo' => 'bar'), UrlGeneratorInterface::ABSOLUTE_PATH);
        $relativeUrlWithoutParameter = $projectUrlGenerator->generate('Test2', array(), UrlGeneratorInterface::ABSOLUTE_PATH);

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
        include $this->testTmpFilepath;

        $projectUrlGenerator = new \WithoutRoutesUrlGenerator(new RequestContext('/app.php'));

        $projectUrlGenerator->generate('Test', array());
    }

    /**
     * @expectedException \Symfony\Component\Routing\Exception\RouteNotFoundException
     */
    public function testGenerateNonExistingRoute()
    {
        $this->routeCollection->add('Test', new Route('/test'));

        file_put_contents($this->testTmpFilepath, $this->generatorDumper->dump(array('class' => 'NonExistingRoutesUrlGenerator')));
        include $this->testTmpFilepath;

        $projectUrlGenerator = new \NonExistingRoutesUrlGenerator(new RequestContext());
        $url = $projectUrlGenerator->generate('NonExisting', array());
    }

    public function testDumpForRouteWithDefaults()
    {
        $this->routeCollection->add('Test', new Route('/testing/{foo}', array('foo' => 'bar')));

        file_put_contents($this->testTmpFilepath, $this->generatorDumper->dump(array('class' => 'DefaultRoutesUrlGenerator')));
        include $this->testTmpFilepath;

        $projectUrlGenerator = new \DefaultRoutesUrlGenerator(new RequestContext());
        $url = $projectUrlGenerator->generate('Test', array());

        $this->assertEquals($url, '/testing');
    }

    public function testDumpWithSchemeRequirement()
    {
        $this->routeCollection->add('Test1', new Route('/testing', array(), array(), array(), '', array('ftp', 'https')));

        file_put_contents($this->testTmpFilepath, $this->generatorDumper->dump(array('class' => 'SchemeUrlGenerator')));
        include $this->testTmpFilepath;

        $projectUrlGenerator = new \SchemeUrlGenerator(new RequestContext('/app.php'));

        $absoluteUrl = $projectUrlGenerator->generate('Test1', array(), UrlGeneratorInterface::ABSOLUTE_URL);
        $relativeUrl = $projectUrlGenerator->generate('Test1', array(), UrlGeneratorInterface::ABSOLUTE_PATH);

        $this->assertEquals($absoluteUrl, 'ftp://localhost/app.php/testing');
        $this->assertEquals($relativeUrl, 'ftp://localhost/app.php/testing');

        $projectUrlGenerator = new \SchemeUrlGenerator(new RequestContext('/app.php', 'GET', 'localhost', 'https'));

        $absoluteUrl = $projectUrlGenerator->generate('Test1', array(), UrlGeneratorInterface::ABSOLUTE_URL);
        $relativeUrl = $projectUrlGenerator->generate('Test1', array(), UrlGeneratorInterface::ABSOLUTE_PATH);

        $this->assertEquals($absoluteUrl, 'https://localhost/app.php/testing');
        $this->assertEquals($relativeUrl, '/app.php/testing');
    }
}
