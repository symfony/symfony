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

use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Generator\Dumper\PhpGeneratorDumper;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

class PhpGeneratorDumperTest extends TestCase
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
        $this->testTmpFilepath = sys_get_temp_dir().\DIRECTORY_SEPARATOR.'php_generator.'.$this->getName().'.php';
        $this->largeTestTmpFilepath = sys_get_temp_dir().\DIRECTORY_SEPARATOR.'php_generator.'.$this->getName().'.large.php';
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

        $absoluteUrlWithParameter = $projectUrlGenerator->generate('Test', ['foo' => 'bar'], UrlGeneratorInterface::ABSOLUTE_URL);
        $absoluteUrlWithoutParameter = $projectUrlGenerator->generate('Test2', [], UrlGeneratorInterface::ABSOLUTE_URL);
        $relativeUrlWithParameter = $projectUrlGenerator->generate('Test', ['foo' => 'bar'], UrlGeneratorInterface::ABSOLUTE_PATH);
        $relativeUrlWithoutParameter = $projectUrlGenerator->generate('Test2', [], UrlGeneratorInterface::ABSOLUTE_PATH);

        $this->assertEquals('http://localhost/app.php/testing/bar', $absoluteUrlWithParameter);
        $this->assertEquals('http://localhost/app.php/testing2', $absoluteUrlWithoutParameter);
        $this->assertEquals('/app.php/testing/bar', $relativeUrlWithParameter);
        $this->assertEquals('/app.php/testing2', $relativeUrlWithoutParameter);
    }

    public function testDumpWithTooManyRoutes()
    {
        if (\defined('HHVM_VERSION_ID')) {
            $this->markTestSkipped('HHVM consumes too much memory on this test.');
        }

        $this->routeCollection->add('Test', new Route('/testing/{foo}'));
        for ($i = 0; $i < 32769; ++$i) {
            $this->routeCollection->add('route_'.$i, new Route('/route_'.$i));
        }
        $this->routeCollection->add('Test2', new Route('/testing2'));

        file_put_contents($this->largeTestTmpFilepath, $this->generatorDumper->dump([
            'class' => 'ProjectLargeUrlGenerator',
        ]));
        $this->routeCollection = $this->generatorDumper = null;
        include $this->largeTestTmpFilepath;

        $projectUrlGenerator = new \ProjectLargeUrlGenerator(new RequestContext('/app.php'));

        $absoluteUrlWithParameter = $projectUrlGenerator->generate('Test', ['foo' => 'bar'], UrlGeneratorInterface::ABSOLUTE_URL);
        $absoluteUrlWithoutParameter = $projectUrlGenerator->generate('Test2', [], UrlGeneratorInterface::ABSOLUTE_URL);
        $relativeUrlWithParameter = $projectUrlGenerator->generate('Test', ['foo' => 'bar'], UrlGeneratorInterface::ABSOLUTE_PATH);
        $relativeUrlWithoutParameter = $projectUrlGenerator->generate('Test2', [], UrlGeneratorInterface::ABSOLUTE_PATH);

        $this->assertEquals('http://localhost/app.php/testing/bar', $absoluteUrlWithParameter);
        $this->assertEquals('http://localhost/app.php/testing2', $absoluteUrlWithoutParameter);
        $this->assertEquals('/app.php/testing/bar', $relativeUrlWithParameter);
        $this->assertEquals('/app.php/testing2', $relativeUrlWithoutParameter);
    }

    public function testDumpWithoutRoutes()
    {
        $this->expectException('InvalidArgumentException');
        file_put_contents($this->testTmpFilepath, $this->generatorDumper->dump(['class' => 'WithoutRoutesUrlGenerator']));
        include $this->testTmpFilepath;

        $projectUrlGenerator = new \WithoutRoutesUrlGenerator(new RequestContext('/app.php'));

        $projectUrlGenerator->generate('Test', []);
    }

    public function testGenerateNonExistingRoute()
    {
        $this->expectException('Symfony\Component\Routing\Exception\RouteNotFoundException');
        $this->routeCollection->add('Test', new Route('/test'));

        file_put_contents($this->testTmpFilepath, $this->generatorDumper->dump(['class' => 'NonExistingRoutesUrlGenerator']));
        include $this->testTmpFilepath;

        $projectUrlGenerator = new \NonExistingRoutesUrlGenerator(new RequestContext());
        $projectUrlGenerator->generate('NonExisting', []);
    }

    public function testDumpForRouteWithDefaults()
    {
        $this->routeCollection->add('Test', new Route('/testing/{foo}', ['foo' => 'bar']));

        file_put_contents($this->testTmpFilepath, $this->generatorDumper->dump(['class' => 'DefaultRoutesUrlGenerator']));
        include $this->testTmpFilepath;

        $projectUrlGenerator = new \DefaultRoutesUrlGenerator(new RequestContext());
        $url = $projectUrlGenerator->generate('Test', []);

        $this->assertEquals('/testing', $url);
    }

    public function testDumpWithSchemeRequirement()
    {
        $this->routeCollection->add('Test1', new Route('/testing', [], [], [], '', ['ftp', 'https']));

        file_put_contents($this->testTmpFilepath, $this->generatorDumper->dump(['class' => 'SchemeUrlGenerator']));
        include $this->testTmpFilepath;

        $projectUrlGenerator = new \SchemeUrlGenerator(new RequestContext('/app.php'));

        $absoluteUrl = $projectUrlGenerator->generate('Test1', [], UrlGeneratorInterface::ABSOLUTE_URL);
        $relativeUrl = $projectUrlGenerator->generate('Test1', [], UrlGeneratorInterface::ABSOLUTE_PATH);

        $this->assertEquals('ftp://localhost/app.php/testing', $absoluteUrl);
        $this->assertEquals('ftp://localhost/app.php/testing', $relativeUrl);

        $projectUrlGenerator = new \SchemeUrlGenerator(new RequestContext('/app.php', 'GET', 'localhost', 'https'));

        $absoluteUrl = $projectUrlGenerator->generate('Test1', [], UrlGeneratorInterface::ABSOLUTE_URL);
        $relativeUrl = $projectUrlGenerator->generate('Test1', [], UrlGeneratorInterface::ABSOLUTE_PATH);

        $this->assertEquals('https://localhost/app.php/testing', $absoluteUrl);
        $this->assertEquals('/app.php/testing', $relativeUrl);
    }
}
