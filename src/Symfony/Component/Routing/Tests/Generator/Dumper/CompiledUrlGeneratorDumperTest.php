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
use Symfony\Bridge\PhpUnit\ExpectDeprecationTrait;
use Symfony\Component\Routing\Exception\RouteCircularReferenceException;
use Symfony\Component\Routing\Exception\RouteNotFoundException;
use Symfony\Component\Routing\Generator\CompiledUrlGenerator;
use Symfony\Component\Routing\Generator\Dumper\CompiledUrlGeneratorDumper;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

class CompiledUrlGeneratorDumperTest extends TestCase
{
    use ExpectDeprecationTrait;

    /**
     * @var RouteCollection
     */
    private $routeCollection;

    /**
     * @var CompiledUrlGeneratorDumper
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

    protected function setUp(): void
    {
        parent::setUp();

        $this->routeCollection = new RouteCollection();
        $this->generatorDumper = new CompiledUrlGeneratorDumper($this->routeCollection);
        $this->testTmpFilepath = sys_get_temp_dir().'/php_generator.'.$this->getName().'.php';
        $this->largeTestTmpFilepath = sys_get_temp_dir().'/php_generator.'.$this->getName().'.large.php';
        @unlink($this->testTmpFilepath);
        @unlink($this->largeTestTmpFilepath);
    }

    protected function tearDown(): void
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

        $projectUrlGenerator = new CompiledUrlGenerator(require $this->testTmpFilepath, new RequestContext('/app.php'));

        $absoluteUrlWithParameter = $projectUrlGenerator->generate('Test', ['foo' => 'bar'], UrlGeneratorInterface::ABSOLUTE_URL);
        $absoluteUrlWithoutParameter = $projectUrlGenerator->generate('Test2', [], UrlGeneratorInterface::ABSOLUTE_URL);
        $relativeUrlWithParameter = $projectUrlGenerator->generate('Test', ['foo' => 'bar'], UrlGeneratorInterface::ABSOLUTE_PATH);
        $relativeUrlWithoutParameter = $projectUrlGenerator->generate('Test2', [], UrlGeneratorInterface::ABSOLUTE_PATH);

        $this->assertEquals('http://localhost/app.php/testing/bar', $absoluteUrlWithParameter);
        $this->assertEquals('http://localhost/app.php/testing2', $absoluteUrlWithoutParameter);
        $this->assertEquals('/app.php/testing/bar', $relativeUrlWithParameter);
        $this->assertEquals('/app.php/testing2', $relativeUrlWithoutParameter);
    }

    public function testDumpWithSimpleLocalizedRoutes()
    {
        $this->routeCollection->add('test', new Route('/foo'));
        $this->routeCollection->add('test.en', (new Route('/testing/is/fun'))->setDefault('_locale', 'en')->setDefault('_canonical_route', 'test')->setRequirement('_locale', 'en'));
        $this->routeCollection->add('test.nl', (new Route('/testen/is/leuk'))->setDefault('_locale', 'nl')->setDefault('_canonical_route', 'test')->setRequirement('_locale', 'nl'));

        $code = $this->generatorDumper->dump();
        file_put_contents($this->testTmpFilepath, $code);

        $context = new RequestContext('/app.php');
        $projectUrlGenerator = new CompiledUrlGenerator(require $this->testTmpFilepath, $context, null, 'en');

        $urlWithDefaultLocale = $projectUrlGenerator->generate('test');
        $urlWithSpecifiedLocale = $projectUrlGenerator->generate('test', ['_locale' => 'nl']);
        $context->setParameter('_locale', 'en');
        $urlWithEnglishContext = $projectUrlGenerator->generate('test');
        $context->setParameter('_locale', 'nl');
        $urlWithDutchContext = $projectUrlGenerator->generate('test');

        $this->assertEquals('/app.php/testing/is/fun', $urlWithDefaultLocale);
        $this->assertEquals('/app.php/testen/is/leuk', $urlWithSpecifiedLocale);
        $this->assertEquals('/app.php/testing/is/fun', $urlWithEnglishContext);
        $this->assertEquals('/app.php/testen/is/leuk', $urlWithDutchContext);

        // test with full route name
        $this->assertEquals('/app.php/testing/is/fun', $projectUrlGenerator->generate('test.en'));

        $context->setParameter('_locale', 'de_DE');
        // test that it fall backs to another route when there is no matching localized route
        $this->assertEquals('/app.php/foo', $projectUrlGenerator->generate('test'));
    }

    public function testDumpWithRouteNotFoundLocalizedRoutes()
    {
        $this->expectException(RouteNotFoundException::class);
        $this->expectExceptionMessage('Unable to generate a URL for the named route "test" as such route does not exist.');
        $this->routeCollection->add('test.en', (new Route('/testing/is/fun'))->setDefault('_locale', 'en')->setDefault('_canonical_route', 'test')->setRequirement('_locale', 'en'));

        $code = $this->generatorDumper->dump();
        file_put_contents($this->testTmpFilepath, $code);

        $projectUrlGenerator = new CompiledUrlGenerator(require $this->testTmpFilepath, new RequestContext('/app.php'), null, 'pl_PL');
        $projectUrlGenerator->generate('test');
    }

    public function testDumpWithFallbackLocaleLocalizedRoutes()
    {
        $this->routeCollection->add('test.en', (new Route('/testing/is/fun'))->setDefault('_locale', 'en')->setDefault('_canonical_route', 'test')->setRequirement('_locale', 'en'));
        $this->routeCollection->add('test.nl', (new Route('/testen/is/leuk'))->setDefault('_locale', 'nl')->setDefault('_canonical_route', 'test')->setRequirement('_locale', 'nl'));
        $this->routeCollection->add('test.fr', (new Route('/tester/est/amusant'))->setDefault('_locale', 'fr')->setDefault('_canonical_route', 'test')->setRequirement('_locale', 'fr'));

        $code = $this->generatorDumper->dump();
        file_put_contents($this->testTmpFilepath, $code);

        $context = new RequestContext('/app.php');
        $context->setParameter('_locale', 'en_GB');
        $projectUrlGenerator = new CompiledUrlGenerator(require $this->testTmpFilepath, $context, null, null);

        // test with context _locale
        $this->assertEquals('/app.php/testing/is/fun', $projectUrlGenerator->generate('test'));
        // test with parameters _locale
        $this->assertEquals('/app.php/testen/is/leuk', $projectUrlGenerator->generate('test', ['_locale' => 'nl_BE']));

        $projectUrlGenerator = new CompiledUrlGenerator(require $this->testTmpFilepath, new RequestContext('/app.php'), null, 'fr_CA');
        // test with default locale
        $this->assertEquals('/app.php/tester/est/amusant', $projectUrlGenerator->generate('test'));
    }

    public function testDumpWithTooManyRoutes()
    {
        $this->routeCollection->add('Test', new Route('/testing/{foo}'));
        for ($i = 0; $i < 32769; ++$i) {
            $this->routeCollection->add('route_'.$i, new Route('/route_'.$i));
        }
        $this->routeCollection->add('Test2', new Route('/testing2'));

        file_put_contents($this->largeTestTmpFilepath, $this->generatorDumper->dump());
        $this->routeCollection = $this->generatorDumper = null;

        $projectUrlGenerator = new CompiledUrlGenerator(require $this->largeTestTmpFilepath, new RequestContext('/app.php'));

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
        $this->expectException(\InvalidArgumentException::class);
        file_put_contents($this->testTmpFilepath, $this->generatorDumper->dump());

        $projectUrlGenerator = new CompiledUrlGenerator(require $this->testTmpFilepath, new RequestContext('/app.php'));

        $projectUrlGenerator->generate('Test', []);
    }

    public function testGenerateNonExistingRoute()
    {
        $this->expectException(RouteNotFoundException::class);
        $this->routeCollection->add('Test', new Route('/test'));

        file_put_contents($this->testTmpFilepath, $this->generatorDumper->dump());

        $projectUrlGenerator = new CompiledUrlGenerator(require $this->testTmpFilepath, new RequestContext());
        $projectUrlGenerator->generate('NonExisting', []);
    }

    public function testDumpForRouteWithDefaults()
    {
        $this->routeCollection->add('Test', new Route('/testing/{foo}', ['foo' => 'bar']));

        file_put_contents($this->testTmpFilepath, $this->generatorDumper->dump());

        $projectUrlGenerator = new CompiledUrlGenerator(require $this->testTmpFilepath, new RequestContext());
        $url = $projectUrlGenerator->generate('Test', []);

        $this->assertEquals('/testing', $url);
    }

    public function testDumpWithSchemeRequirement()
    {
        $this->routeCollection->add('Test1', new Route('/testing', [], [], [], '', ['ftp', 'https']));

        file_put_contents($this->testTmpFilepath, $this->generatorDumper->dump());

        $projectUrlGenerator = new CompiledUrlGenerator(require $this->testTmpFilepath, new RequestContext('/app.php'));

        $absoluteUrl = $projectUrlGenerator->generate('Test1', [], UrlGeneratorInterface::ABSOLUTE_URL);
        $relativeUrl = $projectUrlGenerator->generate('Test1', [], UrlGeneratorInterface::ABSOLUTE_PATH);

        $this->assertEquals('ftp://localhost/app.php/testing', $absoluteUrl);
        $this->assertEquals('ftp://localhost/app.php/testing', $relativeUrl);

        $projectUrlGenerator = new CompiledUrlGenerator(require $this->testTmpFilepath, new RequestContext('/app.php', 'GET', 'localhost', 'https'));

        $absoluteUrl = $projectUrlGenerator->generate('Test1', [], UrlGeneratorInterface::ABSOLUTE_URL);
        $relativeUrl = $projectUrlGenerator->generate('Test1', [], UrlGeneratorInterface::ABSOLUTE_PATH);

        $this->assertEquals('https://localhost/app.php/testing', $absoluteUrl);
        $this->assertEquals('/app.php/testing', $relativeUrl);
    }

    public function testDumpWithLocalizedRoutesPreserveTheGoodLocaleInTheUrl()
    {
        $this->routeCollection->add('foo.en', (new Route('/{_locale}/fork'))->setDefault('_locale', 'en')->setDefault('_canonical_route', 'foo')->setRequirement('_locale', 'en'));
        $this->routeCollection->add('foo.fr', (new Route('/{_locale}/fourchette'))->setDefault('_locale', 'fr')->setDefault('_canonical_route', 'foo')->setRequirement('_locale', 'fr'));
        $this->routeCollection->add('fun.en', (new Route('/fun'))->setDefault('_locale', 'en')->setDefault('_canonical_route', 'fun')->setRequirement('_locale', 'en'));
        $this->routeCollection->add('fun.fr', (new Route('/amusant'))->setDefault('_locale', 'fr')->setDefault('_canonical_route', 'fun')->setRequirement('_locale', 'fr'));

        file_put_contents($this->testTmpFilepath, $this->generatorDumper->dump());

        $requestContext = new RequestContext();
        $requestContext->setParameter('_locale', 'fr');

        $compiledUrlGenerator = new CompiledUrlGenerator(require $this->testTmpFilepath, $requestContext, null, null);

        $this->assertSame('/fr/fourchette', $compiledUrlGenerator->generate('foo'));
        $this->assertSame('/en/fork', $compiledUrlGenerator->generate('foo.en'));
        $this->assertSame('/en/fork', $compiledUrlGenerator->generate('foo', ['_locale' => 'en']));
        $this->assertSame('/fr/fourchette', $compiledUrlGenerator->generate('foo.fr', ['_locale' => 'en']));

        $this->assertSame('/amusant', $compiledUrlGenerator->generate('fun'));
        $this->assertSame('/fun', $compiledUrlGenerator->generate('fun.en'));
        $this->assertSame('/fun', $compiledUrlGenerator->generate('fun', ['_locale' => 'en']));
        $this->assertSame('/amusant', $compiledUrlGenerator->generate('fun.fr', ['_locale' => 'en']));
    }

    public function testAliases()
    {
        $subCollection = new RouteCollection();
        $subCollection->add('a', new Route('/sub'));
        $subCollection->addAlias('b', 'a');
        $subCollection->addAlias('c', 'b');
        $subCollection->addNamePrefix('sub_');

        $this->routeCollection->add('a', new Route('/foo'));
        $this->routeCollection->addAlias('b', 'a');
        $this->routeCollection->addAlias('c', 'b');
        $this->routeCollection->addCollection($subCollection);

        file_put_contents($this->testTmpFilepath, $this->generatorDumper->dump());

        $compiledUrlGenerator = new CompiledUrlGenerator(require $this->testTmpFilepath, new RequestContext());

        $this->assertSame('/foo', $compiledUrlGenerator->generate('b'));
        $this->assertSame('/foo', $compiledUrlGenerator->generate('c'));
        $this->assertSame('/sub', $compiledUrlGenerator->generate('sub_b'));
        $this->assertSame('/sub', $compiledUrlGenerator->generate('sub_c'));
    }

    public function testTargetAliasNotExisting()
    {
        $this->expectException(RouteNotFoundException::class);

        $this->routeCollection->addAlias('a', 'not-existing');

        file_put_contents($this->testTmpFilepath, $this->generatorDumper->dump());

        $compiledUrlGenerator = new CompiledUrlGenerator(require $this->testTmpFilepath, new RequestContext());

        $compiledUrlGenerator->generate('a');
    }

    public function testTargetAliasWithNamePrefixNotExisting()
    {
        $this->expectException(RouteNotFoundException::class);

        $subCollection = new RouteCollection();
        $subCollection->addAlias('a', 'not-existing');
        $subCollection->addNamePrefix('sub_');

        $this->routeCollection->addCollection($subCollection);

        file_put_contents($this->testTmpFilepath, $this->generatorDumper->dump());

        $compiledUrlGenerator = new CompiledUrlGenerator(require $this->testTmpFilepath, new RequestContext());

        $compiledUrlGenerator->generate('sub_a');
    }

    public function testCircularReferenceShouldThrowAnException()
    {
        $this->expectException(RouteCircularReferenceException::class);
        $this->expectExceptionMessage('Circular reference detected for route "b", path: "b -> a -> b".');

        $this->routeCollection->addAlias('a', 'b');
        $this->routeCollection->addAlias('b', 'a');

        $this->generatorDumper->dump();
    }

    public function testDeepCircularReferenceShouldThrowAnException()
    {
        $this->expectException(RouteCircularReferenceException::class);
        $this->expectExceptionMessage('Circular reference detected for route "b", path: "b -> c -> b".');

        $this->routeCollection->addAlias('a', 'b');
        $this->routeCollection->addAlias('b', 'c');
        $this->routeCollection->addAlias('c', 'b');

        $this->generatorDumper->dump();
    }

    public function testIndirectCircularReferenceShouldThrowAnException()
    {
        $this->expectException(RouteCircularReferenceException::class);
        $this->expectExceptionMessage('Circular reference detected for route "b", path: "b -> c -> a -> b".');

        $this->routeCollection->addAlias('a', 'b');
        $this->routeCollection->addAlias('b', 'c');
        $this->routeCollection->addAlias('c', 'a');

        $this->generatorDumper->dump();
    }

    /**
     * @group legacy
     */
    public function testDeprecatedAlias()
    {
        $this->expectDeprecation('Since foo/bar 1.0.0: The "b" route alias is deprecated. You should stop using it, as it will be removed in the future.');

        $this->routeCollection->add('a', new Route('/foo'));
        $this->routeCollection->addAlias('b', 'a')
            ->setDeprecated('foo/bar', '1.0.0', '');

        file_put_contents($this->testTmpFilepath, $this->generatorDumper->dump());

        $compiledUrlGenerator = new CompiledUrlGenerator(require $this->testTmpFilepath, new RequestContext());

        $compiledUrlGenerator->generate('b');
    }

    /**
     * @group legacy
     */
    public function testDeprecatedAliasWithCustomMessage()
    {
        $this->expectDeprecation('Since foo/bar 1.0.0: foo b.');

        $this->routeCollection->add('a', new Route('/foo'));
        $this->routeCollection->addAlias('b', 'a')
            ->setDeprecated('foo/bar', '1.0.0', 'foo %alias_id%.');

        file_put_contents($this->testTmpFilepath, $this->generatorDumper->dump());

        $compiledUrlGenerator = new CompiledUrlGenerator(require $this->testTmpFilepath, new RequestContext());

        $compiledUrlGenerator->generate('b');
    }

    /**
     * @group legacy
     */
    public function testTargettingADeprecatedAliasShouldTriggerDeprecation()
    {
        $this->expectDeprecation('Since foo/bar 1.0.0: foo b.');

        $this->routeCollection->add('a', new Route('/foo'));
        $this->routeCollection->addAlias('b', 'a')
            ->setDeprecated('foo/bar', '1.0.0', 'foo %alias_id%.');
        $this->routeCollection->addAlias('c', 'b');

        $this->generatorDumper->dump();
    }
}
