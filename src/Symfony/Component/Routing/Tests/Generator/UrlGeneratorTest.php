<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Routing\Tests\Generator;

use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\PhpUnit\ExpectDeprecationTrait;
use Symfony\Component\Routing\Exception\InvalidParameterException;
use Symfony\Component\Routing\Exception\MissingMandatoryParametersException;
use Symfony\Component\Routing\Exception\RouteCircularReferenceException;
use Symfony\Component\Routing\Exception\RouteNotFoundException;
use Symfony\Component\Routing\Generator\UrlGenerator;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

class UrlGeneratorTest extends TestCase
{
    use ExpectDeprecationTrait;

    public function testAbsoluteUrlWithPort80()
    {
        $routes = $this->getRoutes('test', new Route('/testing'));
        $url = $this->getGenerator($routes)->generate('test', [], UrlGeneratorInterface::ABSOLUTE_URL);

        self::assertEquals('http://localhost/app.php/testing', $url);
    }

    public function testAbsoluteSecureUrlWithPort443()
    {
        $routes = $this->getRoutes('test', new Route('/testing'));
        $url = $this->getGenerator($routes, ['scheme' => 'https'])->generate('test', [], UrlGeneratorInterface::ABSOLUTE_URL);

        self::assertEquals('https://localhost/app.php/testing', $url);
    }

    public function testAbsoluteUrlWithNonStandardPort()
    {
        $routes = $this->getRoutes('test', new Route('/testing'));
        $url = $this->getGenerator($routes, ['httpPort' => 8080])->generate('test', [], UrlGeneratorInterface::ABSOLUTE_URL);

        self::assertEquals('http://localhost:8080/app.php/testing', $url);
    }

    public function testAbsoluteSecureUrlWithNonStandardPort()
    {
        $routes = $this->getRoutes('test', new Route('/testing'));
        $url = $this->getGenerator($routes, ['httpsPort' => 8080, 'scheme' => 'https'])->generate('test', [], UrlGeneratorInterface::ABSOLUTE_URL);

        self::assertEquals('https://localhost:8080/app.php/testing', $url);
    }

    public function testRelativeUrlWithoutParameters()
    {
        $routes = $this->getRoutes('test', new Route('/testing'));
        $url = $this->getGenerator($routes)->generate('test', [], UrlGeneratorInterface::ABSOLUTE_PATH);

        self::assertEquals('/app.php/testing', $url);
    }

    public function testRelativeUrlWithParameter()
    {
        $routes = $this->getRoutes('test', new Route('/testing/{foo}'));
        $url = $this->getGenerator($routes)->generate('test', ['foo' => 'bar'], UrlGeneratorInterface::ABSOLUTE_PATH);

        self::assertEquals('/app.php/testing/bar', $url);
    }

    public function testRelativeUrlWithNullParameter()
    {
        $routes = $this->getRoutes('test', new Route('/testing.{format}', ['format' => null]));
        $url = $this->getGenerator($routes)->generate('test', [], UrlGeneratorInterface::ABSOLUTE_PATH);

        self::assertEquals('/app.php/testing', $url);
    }

    public function testRelativeUrlWithNullParameterButNotOptional()
    {
        self::expectException(InvalidParameterException::class);
        $routes = $this->getRoutes('test', new Route('/testing/{foo}/bar', ['foo' => null]));
        // This must raise an exception because the default requirement for "foo" is "[^/]+" which is not met with these params.
        // Generating path "/testing//bar" would be wrong as matching this route would fail.
        $this->getGenerator($routes)->generate('test', [], UrlGeneratorInterface::ABSOLUTE_PATH);
    }

    public function testRelativeUrlWithOptionalZeroParameter()
    {
        $routes = $this->getRoutes('test', new Route('/testing/{page}'));
        $url = $this->getGenerator($routes)->generate('test', ['page' => 0], UrlGeneratorInterface::ABSOLUTE_PATH);

        self::assertEquals('/app.php/testing/0', $url);
    }

    public function testNotPassedOptionalParameterInBetween()
    {
        $routes = $this->getRoutes('test', new Route('/{slug}/{page}', ['slug' => 'index', 'page' => 0]));
        self::assertSame('/app.php/index/1', $this->getGenerator($routes)->generate('test', ['page' => 1]));
        self::assertSame('/app.php/', $this->getGenerator($routes)->generate('test'));
    }

    /**
     * @dataProvider valuesProvider
     */
    public function testRelativeUrlWithExtraParameters(string $expectedQueryString, string $parameter, $value)
    {
        $routes = $this->getRoutes('test', new Route('/testing'));
        $url = $this->getGenerator($routes)->generate('test', [$parameter => $value], UrlGeneratorInterface::ABSOLUTE_PATH);

        self::assertSame('/app.php/testing'.$expectedQueryString, $url);
    }

    /**
     * @dataProvider valuesProvider
     */
    public function testAbsoluteUrlWithExtraParameters(string $expectedQueryString, string $parameter, $value)
    {
        $routes = $this->getRoutes('test', new Route('/testing'));
        $url = $this->getGenerator($routes)->generate('test', [$parameter => $value], UrlGeneratorInterface::ABSOLUTE_URL);

        self::assertSame('http://localhost/app.php/testing'.$expectedQueryString, $url);
    }

    public function valuesProvider(): array
    {
        $stdClass = new \stdClass();
        $stdClass->baz = 'bar';

        $nestedStdClass = new \stdClass();
        $nestedStdClass->nested = $stdClass;

        return [
            'null' => ['', 'foo', null],
            'string' => ['?foo=bar', 'foo', 'bar'],
            'boolean-false' => ['?foo=0', 'foo', false],
            'boolean-true' => ['?foo=1', 'foo', true],
            'object implementing __toString()' => ['?foo=bar', 'foo', new StringableObject()],
            'object implementing __toString() but has public property' => ['?foo%5Bfoo%5D=property', 'foo', new StringableObjectWithPublicProperty()],
            'object implementing __toString() in nested array' => ['?foo%5Bbaz%5D=bar', 'foo', ['baz' => new StringableObject()]],
            'object implementing __toString() in nested array but has public property' => ['?foo%5Bbaz%5D%5Bfoo%5D=property', 'foo', ['baz' => new StringableObjectWithPublicProperty()]],
            'stdClass' => ['?foo%5Bbaz%5D=bar', 'foo', $stdClass],
            'stdClass in nested stdClass' => ['?foo%5Bnested%5D%5Bbaz%5D=bar', 'foo', $nestedStdClass],
            'non stringable object' => ['', 'foo', new NonStringableObject()],
            'non stringable object but has public property' => ['?foo%5Bfoo%5D=property', 'foo', new NonStringableObjectWithPublicProperty()],
        ];
    }

    public function testUrlWithExtraParametersFromGlobals()
    {
        $routes = $this->getRoutes('test', new Route('/testing'));
        $generator = $this->getGenerator($routes);
        $context = new RequestContext('/app.php');
        $context->setParameter('bar', 'bar');
        $generator->setContext($context);
        $url = $generator->generate('test', ['foo' => 'bar']);

        self::assertEquals('/app.php/testing?foo=bar', $url);
    }

    public function testUrlWithGlobalParameter()
    {
        $routes = $this->getRoutes('test', new Route('/testing/{foo}'));
        $generator = $this->getGenerator($routes);
        $context = new RequestContext('/app.php');
        $context->setParameter('foo', 'bar');
        $generator->setContext($context);
        $url = $generator->generate('test', []);

        self::assertEquals('/app.php/testing/bar', $url);
    }

    public function testGlobalParameterHasHigherPriorityThanDefault()
    {
        $routes = $this->getRoutes('test', new Route('/{_locale}', ['_locale' => 'en']));
        $generator = $this->getGenerator($routes);
        $context = new RequestContext('/app.php');
        $context->setParameter('_locale', 'de');
        $generator->setContext($context);
        $url = $generator->generate('test', []);

        self::assertSame('/app.php/de', $url);
    }

    public function testGenerateWithDefaultLocale()
    {
        $routes = new RouteCollection();

        $route = new Route('');

        $name = 'test';

        foreach (['hr' => '/foo', 'en' => '/bar'] as $locale => $path) {
            $localizedRoute = clone $route;
            $localizedRoute->setDefault('_locale', $locale);
            $localizedRoute->setRequirement('_locale', $locale);
            $localizedRoute->setDefault('_canonical_route', $name);
            $localizedRoute->setPath($path);
            $routes->add($name.'.'.$locale, $localizedRoute);
        }

        $generator = $this->getGenerator($routes, [], null, 'hr');

        self::assertSame('http://localhost/app.php/foo', $generator->generate($name, [], UrlGeneratorInterface::ABSOLUTE_URL));
    }

    public function testGenerateWithOverriddenParameterLocale()
    {
        $routes = new RouteCollection();

        $route = new Route('');

        $name = 'test';

        foreach (['hr' => '/foo', 'en' => '/bar'] as $locale => $path) {
            $localizedRoute = clone $route;
            $localizedRoute->setDefault('_locale', $locale);
            $localizedRoute->setRequirement('_locale', $locale);
            $localizedRoute->setDefault('_canonical_route', $name);
            $localizedRoute->setPath($path);
            $routes->add($name.'.'.$locale, $localizedRoute);
        }

        $generator = $this->getGenerator($routes, [], null, 'hr');

        self::assertSame('http://localhost/app.php/bar', $generator->generate($name, ['_locale' => 'en'], UrlGeneratorInterface::ABSOLUTE_URL));
    }

    public function testGenerateWithOverriddenParameterLocaleFromRequestContext()
    {
        $routes = new RouteCollection();

        $route = new Route('');

        $name = 'test';

        foreach (['hr' => '/foo', 'en' => '/bar'] as $locale => $path) {
            $localizedRoute = clone $route;
            $localizedRoute->setDefault('_locale', $locale);
            $localizedRoute->setRequirement('_locale', $locale);
            $localizedRoute->setDefault('_canonical_route', $name);
            $localizedRoute->setPath($path);
            $routes->add($name.'.'.$locale, $localizedRoute);
        }

        $generator = $this->getGenerator($routes, [], null, 'hr');

        $context = new RequestContext('/app.php');
        $context->setParameter('_locale', 'en');
        $generator->setContext($context);

        self::assertSame('http://localhost/app.php/bar', $generator->generate($name, [], UrlGeneratorInterface::ABSOLUTE_URL));
    }

    public function testDumpWithLocalizedRoutesPreserveTheGoodLocaleInTheUrl()
    {
        $routeCollection = new RouteCollection();

        $routeCollection->add('foo.en', (new Route('/{_locale}/fork'))->setDefault('_locale', 'en')->setDefault('_canonical_route', 'foo')->setRequirement('_locale', 'en'));
        $routeCollection->add('foo.fr', (new Route('/{_locale}/fourchette'))->setDefault('_locale', 'fr')->setDefault('_canonical_route', 'foo')->setRequirement('_locale', 'fr'));
        $routeCollection->add('fun.en', (new Route('/fun'))->setDefault('_locale', 'en')->setDefault('_canonical_route', 'fun')->setRequirement('_locale', 'en'));
        $routeCollection->add('fun.fr', (new Route('/amusant'))->setDefault('_locale', 'fr')->setDefault('_canonical_route', 'fun')->setRequirement('_locale', 'fr'));

        $urlGenerator = $this->getGenerator($routeCollection);
        $urlGenerator->getContext()->setParameter('_locale', 'fr');

        self::assertSame('/app.php/fr/fourchette', $urlGenerator->generate('foo'));
        self::assertSame('/app.php/en/fork', $urlGenerator->generate('foo.en'));
        self::assertSame('/app.php/en/fork', $urlGenerator->generate('foo', ['_locale' => 'en']));
        self::assertSame('/app.php/fr/fourchette', $urlGenerator->generate('foo.fr', ['_locale' => 'en']));

        self::assertSame('/app.php/amusant', $urlGenerator->generate('fun'));
        self::assertSame('/app.php/fun', $urlGenerator->generate('fun.en'));
        self::assertSame('/app.php/fun', $urlGenerator->generate('fun', ['_locale' => 'en']));
        self::assertSame('/app.php/amusant', $urlGenerator->generate('fun.fr', ['_locale' => 'en']));
    }

    public function testGenerateWithoutRoutes()
    {
        self::expectException(RouteNotFoundException::class);
        $routes = $this->getRoutes('foo', new Route('/testing/{foo}'));
        $this->getGenerator($routes)->generate('test', [], UrlGeneratorInterface::ABSOLUTE_URL);
    }

    public function testGenerateWithInvalidLocale()
    {
        self::expectException(RouteNotFoundException::class);
        $routes = new RouteCollection();

        $route = new Route('');

        $name = 'test';

        foreach (['hr' => '/foo', 'en' => '/bar'] as $locale => $path) {
            $localizedRoute = clone $route;
            $localizedRoute->setDefault('_locale', $locale);
            $localizedRoute->setRequirement('_locale', $locale);
            $localizedRoute->setDefault('_canonical_route', $name);
            $localizedRoute->setPath($path);
            $routes->add($name.'.'.$locale, $localizedRoute);
        }

        $generator = $this->getGenerator($routes, [], null, 'fr');
        $generator->generate($name);
    }

    public function testGenerateForRouteWithoutMandatoryParameter()
    {
        self::expectException(MissingMandatoryParametersException::class);
        $routes = $this->getRoutes('test', new Route('/testing/{foo}'));
        $this->getGenerator($routes)->generate('test', [], UrlGeneratorInterface::ABSOLUTE_URL);
    }

    public function testGenerateForRouteWithInvalidOptionalParameter()
    {
        self::expectException(InvalidParameterException::class);
        $routes = $this->getRoutes('test', new Route('/testing/{foo}', ['foo' => '1'], ['foo' => 'd+']));
        $this->getGenerator($routes)->generate('test', ['foo' => 'bar'], UrlGeneratorInterface::ABSOLUTE_URL);
    }

    public function testGenerateForRouteWithInvalidParameter()
    {
        self::expectException(InvalidParameterException::class);
        $routes = $this->getRoutes('test', new Route('/testing/{foo}', [], ['foo' => '1|2']));
        $this->getGenerator($routes)->generate('test', ['foo' => '0'], UrlGeneratorInterface::ABSOLUTE_URL);
    }

    public function testGenerateForRouteWithInvalidOptionalParameterNonStrict()
    {
        $routes = $this->getRoutes('test', new Route('/testing/{foo}', ['foo' => '1'], ['foo' => 'd+']));
        $generator = $this->getGenerator($routes);
        $generator->setStrictRequirements(false);
        self::assertSame('', $generator->generate('test', ['foo' => 'bar'], UrlGeneratorInterface::ABSOLUTE_URL));
    }

    public function testGenerateForRouteWithInvalidOptionalParameterNonStrictWithLogger()
    {
        $routes = $this->getRoutes('test', new Route('/testing/{foo}', ['foo' => '1'], ['foo' => 'd+']));
        $logger = self::createMock(LoggerInterface::class);
        $logger->expects(self::once())
            ->method('error');
        $generator = $this->getGenerator($routes, [], $logger);
        $generator->setStrictRequirements(false);
        self::assertSame('', $generator->generate('test', ['foo' => 'bar'], UrlGeneratorInterface::ABSOLUTE_URL));
    }

    public function testGenerateForRouteWithInvalidParameterButDisabledRequirementsCheck()
    {
        $routes = $this->getRoutes('test', new Route('/testing/{foo}', ['foo' => '1'], ['foo' => 'd+']));
        $generator = $this->getGenerator($routes);
        $generator->setStrictRequirements(null);
        self::assertSame('/app.php/testing/bar', $generator->generate('test', ['foo' => 'bar']));
    }

    public function testGenerateForRouteWithInvalidMandatoryParameter()
    {
        self::expectException(InvalidParameterException::class);
        $routes = $this->getRoutes('test', new Route('/testing/{foo}', [], ['foo' => 'd+']));
        $this->getGenerator($routes)->generate('test', ['foo' => 'bar'], UrlGeneratorInterface::ABSOLUTE_URL);
    }

    public function testGenerateForRouteWithInvalidUtf8Parameter()
    {
        self::expectException(InvalidParameterException::class);
        $routes = $this->getRoutes('test', new Route('/testing/{foo}', [], ['foo' => '\pL+'], ['utf8' => true]));
        $this->getGenerator($routes)->generate('test', ['foo' => 'abc123'], UrlGeneratorInterface::ABSOLUTE_URL);
    }

    public function testRequiredParamAndEmptyPassed()
    {
        self::expectException(InvalidParameterException::class);
        $routes = $this->getRoutes('test', new Route('/{slug}', [], ['slug' => '.+']));
        $this->getGenerator($routes)->generate('test', ['slug' => '']);
    }

    public function testSchemeRequirementDoesNothingIfSameCurrentScheme()
    {
        $routes = $this->getRoutes('test', new Route('/', [], [], [], '', ['http']));
        self::assertEquals('/app.php/', $this->getGenerator($routes)->generate('test'));

        $routes = $this->getRoutes('test', new Route('/', [], [], [], '', ['https']));
        self::assertEquals('/app.php/', $this->getGenerator($routes, ['scheme' => 'https'])->generate('test'));
    }

    public function testSchemeRequirementForcesAbsoluteUrl()
    {
        $routes = $this->getRoutes('test', new Route('/', [], [], [], '', ['https']));
        self::assertEquals('https://localhost/app.php/', $this->getGenerator($routes)->generate('test'));

        $routes = $this->getRoutes('test', new Route('/', [], [], [], '', ['http']));
        self::assertEquals('http://localhost/app.php/', $this->getGenerator($routes, ['scheme' => 'https'])->generate('test'));
    }

    public function testSchemeRequirementCreatesUrlForFirstRequiredScheme()
    {
        $routes = $this->getRoutes('test', new Route('/', [], [], [], '', ['Ftp', 'https']));
        self::assertEquals('ftp://localhost/app.php/', $this->getGenerator($routes)->generate('test'));
    }

    public function testPathWithTwoStartingSlashes()
    {
        $routes = $this->getRoutes('test', new Route('//path-and-not-domain'));

        // this must not generate '//path-and-not-domain' because that would be a network path
        self::assertSame('/path-and-not-domain', $this->getGenerator($routes, ['BaseUrl' => ''])->generate('test'));
    }

    public function testNoTrailingSlashForMultipleOptionalParameters()
    {
        $routes = $this->getRoutes('test', new Route('/category/{slug1}/{slug2}/{slug3}', ['slug2' => null, 'slug3' => null]));

        self::assertEquals('/app.php/category/foo', $this->getGenerator($routes)->generate('test', ['slug1' => 'foo']));
    }

    public function testWithAnIntegerAsADefaultValue()
    {
        $routes = $this->getRoutes('test', new Route('/{default}', ['default' => 0]));

        self::assertEquals('/app.php/foo', $this->getGenerator($routes)->generate('test', ['default' => 'foo']));
    }

    public function testNullForOptionalParameterIsIgnored()
    {
        $routes = $this->getRoutes('test', new Route('/test/{default}', ['default' => 0]));

        self::assertEquals('/app.php/test', $this->getGenerator($routes)->generate('test', ['default' => null]));
    }

    public function testQueryParamSameAsDefault()
    {
        $routes = $this->getRoutes('test', new Route('/test', ['page' => 1]));

        self::assertSame('/app.php/test?page=2', $this->getGenerator($routes)->generate('test', ['page' => 2]));
        self::assertSame('/app.php/test', $this->getGenerator($routes)->generate('test', ['page' => 1]));
        self::assertSame('/app.php/test', $this->getGenerator($routes)->generate('test', ['page' => '1']));
        self::assertSame('/app.php/test', $this->getGenerator($routes)->generate('test'));
    }

    public function testArrayQueryParamSameAsDefault()
    {
        $routes = $this->getRoutes('test', new Route('/test', ['array' => ['foo', 'bar']]));

        self::assertSame('/app.php/test?array%5B0%5D=bar&array%5B1%5D=foo', $this->getGenerator($routes)->generate('test', ['array' => ['bar', 'foo']]));
        self::assertSame('/app.php/test?array%5Ba%5D=foo&array%5Bb%5D=bar', $this->getGenerator($routes)->generate('test', ['array' => ['a' => 'foo', 'b' => 'bar']]));
        self::assertSame('/app.php/test', $this->getGenerator($routes)->generate('test', ['array' => ['foo', 'bar']]));
        self::assertSame('/app.php/test', $this->getGenerator($routes)->generate('test', ['array' => [1 => 'bar', 0 => 'foo']]));
        self::assertSame('/app.php/test', $this->getGenerator($routes)->generate('test'));
    }

    public function testGenerateWithSpecialRouteName()
    {
        $routes = $this->getRoutes('$péß^a|', new Route('/bar'));

        self::assertSame('/app.php/bar', $this->getGenerator($routes)->generate('$péß^a|'));
    }

    public function testUrlEncoding()
    {
        $expectedPath = '/app.php/@:%5B%5D/%28%29*%27%22%20+,;-._~%26%24%3C%3E|%7B%7D%25%5C%5E%60!%3Ffoo=bar%23id'
            .'/@:%5B%5D/%28%29*%27%22%20+,;-._~%26%24%3C%3E|%7B%7D%25%5C%5E%60!%3Ffoo=bar%23id'
            .'?query=@:%5B%5D/%28%29*%27%22%20%2B,;-._~%26%24%3C%3E%7C%7B%7D%25%5C%5E%60!?foo%3Dbar%23id';

        // This tests the encoding of reserved characters that are used for delimiting of URI components (defined in RFC 3986)
        // and other special ASCII chars. These chars are tested as static text path, variable path and query param.
        $chars = '@:[]/()*\'" +,;-._~&$<>|{}%\\^`!?foo=bar#id';
        $routes = $this->getRoutes('test', new Route("/$chars/{varpath}", [], ['varpath' => '.+']));
        self::assertSame($expectedPath, $this->getGenerator($routes)->generate('test', [
            'varpath' => $chars,
            'query' => $chars,
        ]));
    }

    public function testEncodingOfRelativePathSegments()
    {
        $routes = $this->getRoutes('test', new Route('/dir/../dir/..'));
        self::assertSame('/app.php/dir/%2E%2E/dir/%2E%2E', $this->getGenerator($routes)->generate('test'));
        $routes = $this->getRoutes('test', new Route('/dir/./dir/.'));
        self::assertSame('/app.php/dir/%2E/dir/%2E', $this->getGenerator($routes)->generate('test'));
        $routes = $this->getRoutes('test', new Route('/a./.a/a../..a/...'));
        self::assertSame('/app.php/a./.a/a../..a/...', $this->getGenerator($routes)->generate('test'));
    }

    public function testEncodingOfSlashInPath()
    {
        $routes = $this->getRoutes('test', new Route('/dir/{path}/dir2', [], ['path' => '.+']));
        self::assertSame('/app.php/dir/foo/bar%2Fbaz/dir2', $this->getGenerator($routes)->generate('test', ['path' => 'foo/bar%2Fbaz']));
    }

    public function testAdjacentVariables()
    {
        $routes = $this->getRoutes('test', new Route('/{x}{y}{z}.{_format}', ['z' => 'default-z', '_format' => 'html'], ['y' => '\d+']));
        $generator = $this->getGenerator($routes);
        self::assertSame('/app.php/foo123', $generator->generate('test', ['x' => 'foo', 'y' => '123']));
        self::assertSame('/app.php/foo123bar.xml', $generator->generate('test', ['x' => 'foo', 'y' => '123', 'z' => 'bar', '_format' => 'xml']));

        // The default requirement for 'x' should not allow the separator '.' in this case because it would otherwise match everything
        // and following optional variables like _format could never match.
        self::expectException(InvalidParameterException::class);
        $generator->generate('test', ['x' => 'do.t', 'y' => '123', 'z' => 'bar', '_format' => 'xml']);
    }

    public function testOptionalVariableWithNoRealSeparator()
    {
        $routes = $this->getRoutes('test', new Route('/get{what}', ['what' => 'All']));
        $generator = $this->getGenerator($routes);

        self::assertSame('/app.php/get', $generator->generate('test'));
        self::assertSame('/app.php/getSites', $generator->generate('test', ['what' => 'Sites']));
    }

    public function testRequiredVariableWithNoRealSeparator()
    {
        $routes = $this->getRoutes('test', new Route('/get{what}Suffix'));
        $generator = $this->getGenerator($routes);

        self::assertSame('/app.php/getSitesSuffix', $generator->generate('test', ['what' => 'Sites']));
    }

    public function testDefaultRequirementOfVariable()
    {
        $routes = $this->getRoutes('test', new Route('/{page}.{_format}'));
        $generator = $this->getGenerator($routes);

        self::assertSame('/app.php/index.mobile.html', $generator->generate('test', ['page' => 'index', '_format' => 'mobile.html']));
    }

    public function testImportantVariable()
    {
        $routes = $this->getRoutes('test', (new Route('/{page}.{!_format}'))->addDefaults(['_format' => 'mobile.html']));
        $generator = $this->getGenerator($routes);

        self::assertSame('/app.php/index.xml', $generator->generate('test', ['page' => 'index', '_format' => 'xml']));
        self::assertSame('/app.php/index.mobile.html', $generator->generate('test', ['page' => 'index', '_format' => 'mobile.html']));
        self::assertSame('/app.php/index.mobile.html', $generator->generate('test', ['page' => 'index']));
    }

    public function testImportantVariableWithNoDefault()
    {
        self::expectException(MissingMandatoryParametersException::class);
        $routes = $this->getRoutes('test', new Route('/{page}.{!_format}'));
        $generator = $this->getGenerator($routes);

        $generator->generate('test', ['page' => 'index']);
    }

    public function testDefaultRequirementOfVariableDisallowsSlash()
    {
        self::expectException(InvalidParameterException::class);
        $routes = $this->getRoutes('test', new Route('/{page}.{_format}'));
        $this->getGenerator($routes)->generate('test', ['page' => 'index', '_format' => 'sl/ash']);
    }

    public function testDefaultRequirementOfVariableDisallowsNextSeparator()
    {
        self::expectException(InvalidParameterException::class);
        $routes = $this->getRoutes('test', new Route('/{page}.{_format}'));
        $this->getGenerator($routes)->generate('test', ['page' => 'do.t', '_format' => 'html']);
    }

    public function testWithHostDifferentFromContext()
    {
        $routes = $this->getRoutes('test', new Route('/{name}', [], [], [], '{locale}.example.com'));

        self::assertEquals('//fr.example.com/app.php/Fabien', $this->getGenerator($routes)->generate('test', ['name' => 'Fabien', 'locale' => 'fr']));
    }

    public function testWithHostSameAsContext()
    {
        $routes = $this->getRoutes('test', new Route('/{name}', [], [], [], '{locale}.example.com'));

        self::assertEquals('/app.php/Fabien', $this->getGenerator($routes, ['host' => 'fr.example.com'])->generate('test', ['name' => 'Fabien', 'locale' => 'fr']));
    }

    public function testWithHostSameAsContextAndAbsolute()
    {
        $routes = $this->getRoutes('test', new Route('/{name}', [], [], [], '{locale}.example.com'));

        self::assertEquals('http://fr.example.com/app.php/Fabien', $this->getGenerator($routes, ['host' => 'fr.example.com'])->generate('test', ['name' => 'Fabien', 'locale' => 'fr'], UrlGeneratorInterface::ABSOLUTE_URL));
    }

    public function testUrlWithInvalidParameterInHost()
    {
        self::expectException(InvalidParameterException::class);
        $routes = $this->getRoutes('test', new Route('/', [], ['foo' => 'bar'], [], '{foo}.example.com'));
        $this->getGenerator($routes)->generate('test', ['foo' => 'baz'], UrlGeneratorInterface::ABSOLUTE_PATH);
    }

    public function testUrlWithInvalidParameterInHostWhenParamHasADefaultValue()
    {
        self::expectException(InvalidParameterException::class);
        $routes = $this->getRoutes('test', new Route('/', ['foo' => 'bar'], ['foo' => 'bar'], [], '{foo}.example.com'));
        $this->getGenerator($routes)->generate('test', ['foo' => 'baz'], UrlGeneratorInterface::ABSOLUTE_PATH);
    }

    public function testUrlWithInvalidParameterEqualsDefaultValueInHost()
    {
        self::expectException(InvalidParameterException::class);
        $routes = $this->getRoutes('test', new Route('/', ['foo' => 'baz'], ['foo' => 'bar'], [], '{foo}.example.com'));
        $this->getGenerator($routes)->generate('test', ['foo' => 'baz'], UrlGeneratorInterface::ABSOLUTE_PATH);
    }

    public function testUrlWithInvalidParameterInHostInNonStrictMode()
    {
        $routes = $this->getRoutes('test', new Route('/', [], ['foo' => 'bar'], [], '{foo}.example.com'));
        $generator = $this->getGenerator($routes);
        $generator->setStrictRequirements(false);
        self::assertSame('', $generator->generate('test', ['foo' => 'baz'], UrlGeneratorInterface::ABSOLUTE_PATH));
    }

    public function testHostIsCaseInsensitive()
    {
        $routes = $this->getRoutes('test', new Route('/', [], ['locale' => 'en|de|fr'], [], '{locale}.FooBar.com'));
        $generator = $this->getGenerator($routes);
        self::assertSame('//EN.FooBar.com/app.php/', $generator->generate('test', ['locale' => 'EN'], UrlGeneratorInterface::NETWORK_PATH));
    }

    public function testDefaultHostIsUsedWhenContextHostIsEmpty()
    {
        $routes = $this->getRoutes('test', new Route('/path', ['domain' => 'my.fallback.host'], ['domain' => '.+'], [], '{domain}'));

        $generator = $this->getGenerator($routes);
        $generator->getContext()->setHost('');

        self::assertSame('http://my.fallback.host/app.php/path', $generator->generate('test', [], UrlGeneratorInterface::ABSOLUTE_URL));
    }

    public function testDefaultHostIsUsedWhenContextHostIsEmptyAndPathReferenceType()
    {
        $routes = $this->getRoutes('test', new Route('/path', ['domain' => 'my.fallback.host'], ['domain' => '.+'], [], '{domain}'));

        $generator = $this->getGenerator($routes);
        $generator->getContext()->setHost('');

        self::assertSame('//my.fallback.host/app.php/path', $generator->generate('test', [], UrlGeneratorInterface::ABSOLUTE_PATH));
    }

    public function testAbsoluteUrlFallbackToPathIfHostIsEmptyAndSchemeIsHttp()
    {
        $routes = $this->getRoutes('test', new Route('/route'));

        $generator = $this->getGenerator($routes);
        $generator->getContext()->setHost('');
        $generator->getContext()->setScheme('https');

        self::assertSame('/app.php/route', $generator->generate('test', [], UrlGeneratorInterface::ABSOLUTE_URL));
    }

    public function testAbsoluteUrlFallbackToNetworkIfSchemeIsEmptyAndHostIsNot()
    {
        $routes = $this->getRoutes('test', new Route('/path'));

        $generator = $this->getGenerator($routes);
        $generator->getContext()->setHost('example.com');
        $generator->getContext()->setScheme('');

        self::assertSame('//example.com/app.php/path', $generator->generate('test', [], UrlGeneratorInterface::ABSOLUTE_URL));
    }

    public function testAbsoluteUrlFallbackToPathIfSchemeAndHostAreEmpty()
    {
        $routes = $this->getRoutes('test', new Route('/path'));

        $generator = $this->getGenerator($routes);
        $generator->getContext()->setHost('');
        $generator->getContext()->setScheme('');

        self::assertSame('/app.php/path', $generator->generate('test', [], UrlGeneratorInterface::ABSOLUTE_URL));
    }

    public function testAbsoluteUrlWithNonHttpSchemeAndEmptyHost()
    {
        $routes = $this->getRoutes('test', new Route('/path', [], [], [], '', ['file']));

        $generator = $this->getGenerator($routes);
        $generator->getContext()->setBaseUrl('');
        $generator->getContext()->setHost('');

        self::assertSame('file:///path', $generator->generate('test', [], UrlGeneratorInterface::ABSOLUTE_URL));
    }

    public function testGenerateNetworkPath()
    {
        $routes = $this->getRoutes('test', new Route('/{name}', [], [], [], '{locale}.example.com', ['http']));

        self::assertSame('//fr.example.com/app.php/Fabien', $this->getGenerator($routes)->generate('test',
            ['name' => 'Fabien', 'locale' => 'fr'], UrlGeneratorInterface::NETWORK_PATH), 'network path with different host');
        self::assertSame('//fr.example.com/app.php/Fabien?query=string', $this->getGenerator($routes, ['host' => 'fr.example.com'])->generate('test',
            ['name' => 'Fabien', 'locale' => 'fr', 'query' => 'string'], UrlGeneratorInterface::NETWORK_PATH), 'network path although host same as context');
        self::assertSame('http://fr.example.com/app.php/Fabien', $this->getGenerator($routes, ['scheme' => 'https'])->generate('test',
            ['name' => 'Fabien', 'locale' => 'fr'], UrlGeneratorInterface::NETWORK_PATH), 'absolute URL because scheme requirement does not match context');
        self::assertSame('http://fr.example.com/app.php/Fabien', $this->getGenerator($routes)->generate('test',
            ['name' => 'Fabien', 'locale' => 'fr'], UrlGeneratorInterface::ABSOLUTE_URL), 'absolute URL with same scheme because it is requested');
    }

    public function testGenerateRelativePath()
    {
        $routes = new RouteCollection();
        $routes->add('article', new Route('/{author}/{article}/'));
        $routes->add('comments', new Route('/{author}/{article}/comments'));
        $routes->add('host', new Route('/{article}', [], [], [], '{author}.example.com'));
        $routes->add('scheme', new Route('/{author}/blog', [], [], [], '', ['https']));
        $routes->add('unrelated', new Route('/about'));

        $generator = $this->getGenerator($routes, ['host' => 'example.com', 'pathInfo' => '/fabien/symfony-is-great/']);

        self::assertSame('comments', $generator->generate('comments',
            ['author' => 'fabien', 'article' => 'symfony-is-great'], UrlGeneratorInterface::RELATIVE_PATH));
        self::assertSame('comments?page=2', $generator->generate('comments',
            ['author' => 'fabien', 'article' => 'symfony-is-great', 'page' => 2], UrlGeneratorInterface::RELATIVE_PATH));
        self::assertSame('../twig-is-great/', $generator->generate('article',
            ['author' => 'fabien', 'article' => 'twig-is-great'], UrlGeneratorInterface::RELATIVE_PATH));
        self::assertSame('../../bernhard/forms-are-great/', $generator->generate('article',
            ['author' => 'bernhard', 'article' => 'forms-are-great'], UrlGeneratorInterface::RELATIVE_PATH));
        self::assertSame('//bernhard.example.com/app.php/forms-are-great', $generator->generate('host',
            ['author' => 'bernhard', 'article' => 'forms-are-great'], UrlGeneratorInterface::RELATIVE_PATH));
        self::assertSame('https://example.com/app.php/bernhard/blog', $generator->generate('scheme',
            ['author' => 'bernhard'], UrlGeneratorInterface::RELATIVE_PATH));
        self::assertSame('../../about', $generator->generate('unrelated',
            [], UrlGeneratorInterface::RELATIVE_PATH));
    }

    public function testAliases()
    {
        $routes = new RouteCollection();
        $routes->add('a', new Route('/foo'));
        $routes->addAlias('b', 'a');
        $routes->addAlias('c', 'b');

        $generator = $this->getGenerator($routes);

        self::assertSame('/app.php/foo', $generator->generate('b'));
        self::assertSame('/app.php/foo', $generator->generate('c'));
    }

    public function testAliasWhichTargetRouteDoesntExist()
    {
        self::expectException(RouteNotFoundException::class);

        $routes = new RouteCollection();
        $routes->addAlias('d', 'non-existent');

        $this->getGenerator($routes)->generate('d');
    }

    /**
     * @group legacy
     */
    public function testDeprecatedAlias()
    {
        $this->expectDeprecation('Since foo/bar 1.0.0: The "b" route alias is deprecated. You should stop using it, as it will be removed in the future.');

        $routes = new RouteCollection();
        $routes->add('a', new Route('/foo'));
        $routes->addAlias('b', 'a')
            ->setDeprecated('foo/bar', '1.0.0', '');

        $this->getGenerator($routes)->generate('b');
    }

    /**
     * @group legacy
     */
    public function testDeprecatedAliasWithCustomMessage()
    {
        $this->expectDeprecation('Since foo/bar 1.0.0: foo b.');

        $routes = new RouteCollection();
        $routes->add('a', new Route('/foo'));
        $routes->addAlias('b', 'a')
            ->setDeprecated('foo/bar', '1.0.0', 'foo %alias_id%.');

        $this->getGenerator($routes)->generate('b');
    }

    /**
     * @group legacy
     */
    public function testTargettingADeprecatedAliasShouldTriggerDeprecation()
    {
        $this->expectDeprecation('Since foo/bar 1.0.0: foo b.');

        $routes = new RouteCollection();
        $routes->add('a', new Route('/foo'));
        $routes->addAlias('b', 'a')
            ->setDeprecated('foo/bar', '1.0.0', 'foo %alias_id%.');
        $routes->addAlias('c', 'b');

        $this->getGenerator($routes)->generate('c');
    }

    public function testCircularReferenceShouldThrowAnException()
    {
        self::expectException(RouteCircularReferenceException::class);
        self::expectExceptionMessage('Circular reference detected for route "b", path: "b -> a -> b".');

        $routes = new RouteCollection();
        $routes->addAlias('a', 'b');
        $routes->addAlias('b', 'a');

        $this->getGenerator($routes)->generate('b');
    }

    public function testDeepCircularReferenceShouldThrowAnException()
    {
        self::expectException(RouteCircularReferenceException::class);
        self::expectExceptionMessage('Circular reference detected for route "b", path: "b -> c -> b".');

        $routes = new RouteCollection();
        $routes->addAlias('a', 'b');
        $routes->addAlias('b', 'c');
        $routes->addAlias('c', 'b');

        $this->getGenerator($routes)->generate('b');
    }

    public function testIndirectCircularReferenceShouldThrowAnException()
    {
        self::expectException(RouteCircularReferenceException::class);
        self::expectExceptionMessage('Circular reference detected for route "a", path: "a -> b -> c -> a".');

        $routes = new RouteCollection();
        $routes->addAlias('a', 'b');
        $routes->addAlias('b', 'c');
        $routes->addAlias('c', 'a');

        $this->getGenerator($routes)->generate('a');
    }

    /**
     * @dataProvider provideRelativePaths
     */
    public function testGetRelativePath($sourcePath, $targetPath, $expectedPath)
    {
        self::assertSame($expectedPath, UrlGenerator::getRelativePath($sourcePath, $targetPath));
    }

    public function provideRelativePaths()
    {
        return [
            [
                '/same/dir/',
                '/same/dir/',
                '',
            ],
            [
                '/same/file',
                '/same/file',
                '',
            ],
            [
                '/',
                '/file',
                'file',
            ],
            [
                '/',
                '/dir/file',
                'dir/file',
            ],
            [
                '/dir/file.html',
                '/dir/different-file.html',
                'different-file.html',
            ],
            [
                '/same/dir/extra-file',
                '/same/dir/',
                './',
            ],
            [
                '/parent/dir/',
                '/parent/',
                '../',
            ],
            [
                '/parent/dir/extra-file',
                '/parent/',
                '../',
            ],
            [
                '/a/b/',
                '/x/y/z/',
                '../../x/y/z/',
            ],
            [
                '/a/b/c/d/e',
                '/a/c/d',
                '../../../c/d',
            ],
            [
                '/a/b/c//',
                '/a/b/c/',
                '../',
            ],
            [
                '/a/b/c/',
                '/a/b/c//',
                './/',
            ],
            [
                '/root/a/b/c/',
                '/root/x/b/c/',
                '../../../x/b/c/',
            ],
            [
                '/a/b/c/d/',
                '/a',
                '../../../../a',
            ],
            [
                '/special-chars/sp%20ce/1€/mäh/e=mc²',
                '/special-chars/sp%20ce/1€/<µ>/e=mc²',
                '../<µ>/e=mc²',
            ],
            [
                'not-rooted',
                'dir/file',
                'dir/file',
            ],
            [
                '//dir/',
                '',
                '../../',
            ],
            [
                '/dir/',
                '/dir/file:with-colon',
                './file:with-colon',
            ],
            [
                '/dir/',
                '/dir/subdir/file:with-colon',
                'subdir/file:with-colon',
            ],
            [
                '/dir/',
                '/dir/:subdir/',
                './:subdir/',
            ],
        ];
    }

    public function testFragmentsCanBeAppendedToUrls()
    {
        $routes = $this->getRoutes('test', new Route('/testing'));

        $url = $this->getGenerator($routes)->generate('test', ['_fragment' => 'frag ment'], UrlGeneratorInterface::ABSOLUTE_PATH);
        self::assertEquals('/app.php/testing#frag%20ment', $url);

        $url = $this->getGenerator($routes)->generate('test', ['_fragment' => '0'], UrlGeneratorInterface::ABSOLUTE_PATH);
        self::assertEquals('/app.php/testing#0', $url);
    }

    public function testFragmentsDoNotEscapeValidCharacters()
    {
        $routes = $this->getRoutes('test', new Route('/testing'));
        $url = $this->getGenerator($routes)->generate('test', ['_fragment' => '?/'], UrlGeneratorInterface::ABSOLUTE_PATH);

        self::assertEquals('/app.php/testing#?/', $url);
    }

    public function testFragmentsCanBeDefinedAsDefaults()
    {
        $routes = $this->getRoutes('test', new Route('/testing', ['_fragment' => 'fragment']));
        $url = $this->getGenerator($routes)->generate('test', [], UrlGeneratorInterface::ABSOLUTE_PATH);

        self::assertEquals('/app.php/testing#fragment', $url);
    }

    /**
     * @dataProvider provideLookAroundRequirementsInPath
     */
    public function testLookRoundRequirementsInPath($expected, $path, $requirement)
    {
        $routes = $this->getRoutes('test', new Route($path, [], ['foo' => $requirement, 'baz' => '.+?']));
        self::assertSame($expected, $this->getGenerator($routes)->generate('test', ['foo' => 'a/b', 'baz' => 'c/d/e']));
    }

    public function provideLookAroundRequirementsInPath()
    {
        yield ['/app.php/a/b/b%28ar/c/d/e', '/{foo}/b(ar/{baz}', '.+(?=/b\\(ar/)'];
        yield ['/app.php/a/b/bar/c/d/e', '/{foo}/bar/{baz}', '.+(?!$)'];
        yield ['/app.php/bar/a/b/bam/c/d/e', '/bar/{foo}/bam/{baz}', '(?<=/bar/).+'];
        yield ['/app.php/bar/a/b/bam/c/d/e', '/bar/{foo}/bam/{baz}', '(?<!^).+'];
    }

    protected function getGenerator(RouteCollection $routes, array $parameters = [], $logger = null, string $defaultLocale = null)
    {
        $context = new RequestContext('/app.php');
        foreach ($parameters as $key => $value) {
            $method = 'set'.$key;
            $context->$method($value);
        }

        return new UrlGenerator($routes, $context, $logger, $defaultLocale);
    }

    protected function getRoutes($name, Route $route)
    {
        $routes = new RouteCollection();
        $routes->add($name, $route);

        return $routes;
    }
}

class StringableObject
{
    public function __toString()
    {
        return 'bar';
    }
}

class StringableObjectWithPublicProperty
{
    public $foo = 'property';

    public function __toString()
    {
        return 'bar';
    }
}

class NonStringableObject
{
}

class NonStringableObjectWithPublicProperty
{
    public $foo = 'property';
}
