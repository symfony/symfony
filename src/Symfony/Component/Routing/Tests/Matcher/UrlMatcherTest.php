<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Routing\Tests\Matcher;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Exception\MethodNotAllowedException;
use Symfony\Component\Routing\Exception\NoConfigurationException;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Symfony\Component\Routing\Matcher\UrlMatcher;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

class UrlMatcherTest extends TestCase
{
    public function testNoMethodSoAllowed()
    {
        $coll = new RouteCollection();
        $coll->add('foo', new Route('/foo'));

        $matcher = $this->getUrlMatcher($coll);
        self::assertIsArray($matcher->match('/foo'));
    }

    public function testMethodNotAllowed()
    {
        $coll = new RouteCollection();
        $coll->add('foo', new Route('/foo', [], [], [], '', [], ['post']));

        $matcher = $this->getUrlMatcher($coll);

        try {
            $matcher->match('/foo');
            self::fail();
        } catch (MethodNotAllowedException $e) {
            self::assertEquals(['POST'], $e->getAllowedMethods());
        }
    }

    public function testMethodNotAllowedOnRoot()
    {
        $coll = new RouteCollection();
        $coll->add('foo', new Route('/', [], [], [], '', [], ['GET']));

        $matcher = $this->getUrlMatcher($coll, new RequestContext('', 'POST'));

        try {
            $matcher->match('/');
            self::fail();
        } catch (MethodNotAllowedException $e) {
            self::assertEquals(['GET'], $e->getAllowedMethods());
        }
    }

    public function testHeadAllowedWhenRequirementContainsGet()
    {
        $coll = new RouteCollection();
        $coll->add('foo', new Route('/foo', [], [], [], '', [], ['get']));

        $matcher = $this->getUrlMatcher($coll, new RequestContext('', 'head'));
        self::assertIsArray($matcher->match('/foo'));
    }

    public function testMethodNotAllowedAggregatesAllowedMethods()
    {
        $coll = new RouteCollection();
        $coll->add('foo1', new Route('/foo', [], [], [], '', [], ['post']));
        $coll->add('foo2', new Route('/foo', [], [], [], '', [], ['put', 'delete']));

        $matcher = $this->getUrlMatcher($coll);

        try {
            $matcher->match('/foo');
            self::fail();
        } catch (MethodNotAllowedException $e) {
            self::assertEquals(['POST', 'PUT', 'DELETE'], $e->getAllowedMethods());
        }
    }

    public function testPatternMatchAndParameterReturn()
    {
        $collection = new RouteCollection();
        $collection->add('foo', new Route('/foo/{bar}'));
        $matcher = $this->getUrlMatcher($collection);
        try {
            $matcher->match('/no-match');
            self::fail();
        } catch (ResourceNotFoundException $e) {
        }

        self::assertEquals(['_route' => 'foo', 'bar' => 'baz'], $matcher->match('/foo/baz'));
    }

    public function testDefaultsAreMerged()
    {
        // test that defaults are merged
        $collection = new RouteCollection();
        $collection->add('foo', new Route('/foo/{bar}', ['def' => 'test']));
        $matcher = $this->getUrlMatcher($collection);
        self::assertEquals(['_route' => 'foo', 'bar' => 'baz', 'def' => 'test'], $matcher->match('/foo/baz'));
    }

    public function testMethodIsIgnoredIfNoMethodGiven()
    {
        // test that route "method" is ignored if no method is given in the context
        $collection = new RouteCollection();
        $collection->add('foo', new Route('/foo', [], [], [], '', [], ['get', 'head']));
        $matcher = $this->getUrlMatcher($collection);
        self::assertIsArray($matcher->match('/foo'));

        // route does not match with POST method context
        $matcher = $this->getUrlMatcher($collection, new RequestContext('', 'post'));
        try {
            $matcher->match('/foo');
            self::fail();
        } catch (MethodNotAllowedException $e) {
        }

        // route does match with GET or HEAD method context
        $matcher = $this->getUrlMatcher($collection);
        self::assertIsArray($matcher->match('/foo'));
        $matcher = $this->getUrlMatcher($collection, new RequestContext('', 'head'));
        self::assertIsArray($matcher->match('/foo'));
    }

    public function testRouteWithOptionalVariableAsFirstSegment()
    {
        $collection = new RouteCollection();
        $collection->add('bar', new Route('/{bar}/foo', ['bar' => 'bar'], ['bar' => 'foo|bar']));
        $matcher = $this->getUrlMatcher($collection);
        self::assertEquals(['_route' => 'bar', 'bar' => 'bar'], $matcher->match('/bar/foo'));
        self::assertEquals(['_route' => 'bar', 'bar' => 'foo'], $matcher->match('/foo/foo'));

        $collection = new RouteCollection();
        $collection->add('bar', new Route('/{bar}', ['bar' => 'bar'], ['bar' => 'foo|bar']));
        $matcher = $this->getUrlMatcher($collection);
        self::assertEquals(['_route' => 'bar', 'bar' => 'foo'], $matcher->match('/foo'));
        self::assertEquals(['_route' => 'bar', 'bar' => 'bar'], $matcher->match('/'));
    }

    public function testRouteWithOnlyOptionalVariables()
    {
        $collection = new RouteCollection();
        $collection->add('bar', new Route('/{foo}/{bar}', ['foo' => 'foo', 'bar' => 'bar'], []));
        $matcher = $this->getUrlMatcher($collection);
        self::assertEquals(['_route' => 'bar', 'foo' => 'foo', 'bar' => 'bar'], $matcher->match('/'));
        self::assertEquals(['_route' => 'bar', 'foo' => 'a', 'bar' => 'bar'], $matcher->match('/a'));
        self::assertEquals(['_route' => 'bar', 'foo' => 'a', 'bar' => 'b'], $matcher->match('/a/b'));
    }

    public function testMatchWithPrefixes()
    {
        $collection = new RouteCollection();
        $collection->add('foo', new Route('/{foo}'));
        $collection->addPrefix('/b');
        $collection->addPrefix('/a');

        $matcher = $this->getUrlMatcher($collection);
        self::assertEquals(['_route' => 'foo', 'foo' => 'foo'], $matcher->match('/a/b/foo'));
    }

    public function testMatchWithDynamicPrefix()
    {
        $collection = new RouteCollection();
        $collection->add('foo', new Route('/{foo}'));
        $collection->addPrefix('/b');
        $collection->addPrefix('/{_locale}');

        $matcher = $this->getUrlMatcher($collection);
        self::assertEquals(['_locale' => 'fr', '_route' => 'foo', 'foo' => 'foo'], $matcher->match('/fr/b/foo'));
    }

    public function testMatchSpecialRouteName()
    {
        $collection = new RouteCollection();
        $collection->add('$péß^a|', new Route('/bar'));

        $matcher = $this->getUrlMatcher($collection);
        self::assertEquals(['_route' => '$péß^a|'], $matcher->match('/bar'));
    }

    public function testMatchImportantVariable()
    {
        $collection = new RouteCollection();
        $collection->add('index', new Route('/index.{!_format}', ['_format' => 'xml']));

        $matcher = $this->getUrlMatcher($collection);
        self::assertEquals(['_route' => 'index', '_format' => 'xml'], $matcher->match('/index.xml'));
    }

    public function testShortPathDoesNotMatchImportantVariable()
    {
        self::expectException(ResourceNotFoundException::class);

        $collection = new RouteCollection();
        $collection->add('index', new Route('/index.{!_format}', ['_format' => 'xml']));

        $this->getUrlMatcher($collection)->match('/index');
    }

    public function testTrailingEncodedNewlineIsNotOverlooked()
    {
        self::expectException(ResourceNotFoundException::class);
        $collection = new RouteCollection();
        $collection->add('foo', new Route('/foo'));

        $matcher = $this->getUrlMatcher($collection);
        $matcher->match('/foo%0a');
    }

    public function testMatchNonAlpha()
    {
        $collection = new RouteCollection();
        $chars = '!"$%éà &\'()*+,./:;<=>@ABCDEFGHIJKLMNOPQRSTUVWXYZ\\[]^_`abcdefghijklmnopqrstuvwxyz{|}~-';
        $collection->add('foo', new Route('/{foo}/bar', [], ['foo' => '['.preg_quote($chars).']+'], ['utf8' => true]));

        $matcher = $this->getUrlMatcher($collection);
        self::assertEquals(['_route' => 'foo', 'foo' => $chars], $matcher->match('/'.rawurlencode($chars).'/bar'));
        self::assertEquals(['_route' => 'foo', 'foo' => $chars], $matcher->match('/'.strtr($chars, ['%' => '%25']).'/bar'));
    }

    public function testMatchWithDotMetacharacterInRequirements()
    {
        $collection = new RouteCollection();
        $collection->add('foo', new Route('/{foo}/bar', [], ['foo' => '.+']));

        $matcher = $this->getUrlMatcher($collection);
        self::assertEquals(['_route' => 'foo', 'foo' => "\n"], $matcher->match('/'.urlencode("\n").'/bar'), 'linefeed character is matched');
    }

    public function testMatchOverriddenRoute()
    {
        $collection = new RouteCollection();
        $collection->add('foo', new Route('/foo'));

        $collection1 = new RouteCollection();
        $collection1->add('foo', new Route('/foo1'));

        $collection->addCollection($collection1);

        $matcher = $this->getUrlMatcher($collection);

        self::assertEquals(['_route' => 'foo'], $matcher->match('/foo1'));
        self::expectException(ResourceNotFoundException::class);
        self::assertEquals([], $matcher->match('/foo'));
    }

    public function testMatchRegression()
    {
        $coll = new RouteCollection();
        $coll->add('foo', new Route('/foo/{foo}'));
        $coll->add('bar', new Route('/foo/bar/{foo}'));

        $matcher = $this->getUrlMatcher($coll);
        self::assertEquals(['foo' => 'bar', '_route' => 'bar'], $matcher->match('/foo/bar/bar'));

        $collection = new RouteCollection();
        $collection->add('foo', new Route('/{bar}'));
        $matcher = $this->getUrlMatcher($collection);
        try {
            $matcher->match('/');
            self::fail();
        } catch (ResourceNotFoundException $e) {
        }
    }

    public function testMultipleParams()
    {
        $coll = new RouteCollection();
        $coll->add('foo1', new Route('/foo/{a}/{b}'));
        $coll->add('foo2', new Route('/foo/{a}/test/test/{b}'));
        $coll->add('foo3', new Route('/foo/{a}/{b}/{c}/{d}'));

        $route = $this->getUrlMatcher($coll)->match('/foo/test/test/test/bar')['_route'];

        self::assertEquals('foo2', $route);
    }

    public function testDefaultRequirementForOptionalVariables()
    {
        $coll = new RouteCollection();
        $coll->add('test', new Route('/{page}.{_format}', ['page' => 'index', '_format' => 'html']));

        $matcher = $this->getUrlMatcher($coll);
        self::assertEquals(['page' => 'my-page', '_format' => 'xml', '_route' => 'test'], $matcher->match('/my-page.xml'));
    }

    public function testMatchingIsEager()
    {
        $coll = new RouteCollection();
        $coll->add('test', new Route('/{foo}-{bar}-', [], ['foo' => '.+', 'bar' => '.+']));

        $matcher = $this->getUrlMatcher($coll);
        self::assertEquals(['foo' => 'text1-text2-text3', 'bar' => 'text4', '_route' => 'test'], $matcher->match('/text1-text2-text3-text4-'));
    }

    public function testAdjacentVariables()
    {
        $coll = new RouteCollection();
        $coll->add('test', new Route('/{w}{x}{y}{z}.{_format}', ['z' => 'default-z', '_format' => 'html'], ['y' => 'y|Y']));

        $matcher = $this->getUrlMatcher($coll);
        // 'w' eagerly matches as much as possible and the other variables match the remaining chars.
        // This also shows that the variables w-z must all exclude the separating char (the dot '.' in this case) by default requirement.
        // Otherwise they would also consume '.xml' and _format would never match as it's an optional variable.
        self::assertEquals(['w' => 'wwwww', 'x' => 'x', 'y' => 'Y', 'z' => 'Z', '_format' => 'xml', '_route' => 'test'], $matcher->match('/wwwwwxYZ.xml'));
        // As 'y' has custom requirement and can only be of value 'y|Y', it will leave  'ZZZ' to variable z.
        // So with carefully chosen requirements adjacent variables, can be useful.
        self::assertEquals(['w' => 'wwwww', 'x' => 'x', 'y' => 'y', 'z' => 'ZZZ', '_format' => 'html', '_route' => 'test'], $matcher->match('/wwwwwxyZZZ'));
        // z and _format are optional.
        self::assertEquals(['w' => 'wwwww', 'x' => 'x', 'y' => 'y', 'z' => 'default-z', '_format' => 'html', '_route' => 'test'], $matcher->match('/wwwwwxy'));

        self::expectException(ResourceNotFoundException::class);
        $matcher->match('/wxy.html');
    }

    public function testOptionalVariableWithNoRealSeparator()
    {
        $coll = new RouteCollection();
        $coll->add('test', new Route('/get{what}', ['what' => 'All']));
        $matcher = $this->getUrlMatcher($coll);

        self::assertEquals(['what' => 'All', '_route' => 'test'], $matcher->match('/get'));
        self::assertEquals(['what' => 'Sites', '_route' => 'test'], $matcher->match('/getSites'));

        // Usually the character in front of an optional parameter can be left out, e.g. with pattern '/get/{what}' just '/get' would match.
        // But here the 't' in 'get' is not a separating character, so it makes no sense to match without it.
        self::expectException(ResourceNotFoundException::class);
        $matcher->match('/ge');
    }

    public function testRequiredVariableWithNoRealSeparator()
    {
        $coll = new RouteCollection();
        $coll->add('test', new Route('/get{what}Suffix'));
        $matcher = $this->getUrlMatcher($coll);

        self::assertEquals(['what' => 'Sites', '_route' => 'test'], $matcher->match('/getSitesSuffix'));
    }

    public function testDefaultRequirementOfVariable()
    {
        $coll = new RouteCollection();
        $coll->add('test', new Route('/{page}.{_format}'));
        $matcher = $this->getUrlMatcher($coll);

        self::assertEquals(['page' => 'index', '_format' => 'mobile.html', '_route' => 'test'], $matcher->match('/index.mobile.html'));
    }

    public function testDefaultRequirementOfVariableDisallowsSlash()
    {
        self::expectException(ResourceNotFoundException::class);
        $coll = new RouteCollection();
        $coll->add('test', new Route('/{page}.{_format}'));
        $matcher = $this->getUrlMatcher($coll);

        $matcher->match('/index.sl/ash');
    }

    public function testDefaultRequirementOfVariableDisallowsNextSeparator()
    {
        self::expectException(ResourceNotFoundException::class);
        $coll = new RouteCollection();
        $coll->add('test', new Route('/{page}.{_format}', [], ['_format' => 'html|xml']));
        $matcher = $this->getUrlMatcher($coll);

        $matcher->match('/do.t.html');
    }

    public function testMissingTrailingSlash()
    {
        self::expectException(ResourceNotFoundException::class);
        $coll = new RouteCollection();
        $coll->add('foo', new Route('/foo/'));

        $matcher = $this->getUrlMatcher($coll);
        $matcher->match('/foo');
    }

    public function testExtraTrailingSlash()
    {
        self::getExpectedException() ?: self::expectException(ResourceNotFoundException::class);
        $coll = new RouteCollection();
        $coll->add('foo', new Route('/foo'));

        $matcher = $this->getUrlMatcher($coll);
        $matcher->match('/foo/');
    }

    public function testMissingTrailingSlashForNonSafeMethod()
    {
        self::getExpectedException() ?: self::expectException(ResourceNotFoundException::class);
        $coll = new RouteCollection();
        $coll->add('foo', new Route('/foo/'));

        $context = new RequestContext();
        $context->setMethod('POST');
        $matcher = $this->getUrlMatcher($coll, $context);
        $matcher->match('/foo');
    }

    public function testExtraTrailingSlashForNonSafeMethod()
    {
        self::getExpectedException() ?: self::expectException(ResourceNotFoundException::class);
        $coll = new RouteCollection();
        $coll->add('foo', new Route('/foo'));

        $context = new RequestContext();
        $context->setMethod('POST');
        $matcher = $this->getUrlMatcher($coll, $context);
        $matcher->match('/foo/');
    }

    public function testSchemeRequirement()
    {
        self::getExpectedException() ?: self::expectException(ResourceNotFoundException::class);
        $coll = new RouteCollection();
        $coll->add('foo', new Route('/foo', [], [], [], '', ['https']));
        $matcher = $this->getUrlMatcher($coll);
        $matcher->match('/foo');
    }

    public function testSchemeRequirementForNonSafeMethod()
    {
        self::getExpectedException() ?: self::expectException(ResourceNotFoundException::class);
        $coll = new RouteCollection();
        $coll->add('foo', new Route('/foo', [], [], [], '', ['https']));

        $context = new RequestContext();
        $context->setMethod('POST');
        $matcher = $this->getUrlMatcher($coll, $context);
        $matcher->match('/foo');
    }

    public function testSamePathWithDifferentScheme()
    {
        $coll = new RouteCollection();
        $coll->add('https_route', new Route('/', [], [], [], '', ['https']));
        $coll->add('http_route', new Route('/', [], [], [], '', ['http']));
        $matcher = $this->getUrlMatcher($coll);
        self::assertEquals(['_route' => 'http_route'], $matcher->match('/'));
    }

    public function testCondition()
    {
        self::expectException(ResourceNotFoundException::class);
        $coll = new RouteCollection();
        $route = new Route('/foo');
        $route->setCondition('context.getMethod() == "POST"');
        $coll->add('foo', $route);
        $matcher = $this->getUrlMatcher($coll);
        $matcher->match('/foo');
    }

    public function testRequestCondition()
    {
        $coll = new RouteCollection();
        $route = new Route('/foo/{bar}');
        $route->setCondition('request.getBaseUrl() == "/bar"');
        $coll->add('bar', $route);
        $route = new Route('/foo/{bar}');
        $route->setCondition('request.getBaseUrl() == "/sub/front.php" and request.getPathInfo() == "/foo/bar"');
        $coll->add('foo', $route);
        $matcher = $this->getUrlMatcher($coll, new RequestContext('/sub/front.php'));
        self::assertEquals(['bar' => 'bar', '_route' => 'foo'], $matcher->match('/foo/bar'));
    }

    public function testDecodeOnce()
    {
        $coll = new RouteCollection();
        $coll->add('foo', new Route('/foo/{foo}'));

        $matcher = $this->getUrlMatcher($coll);
        self::assertEquals(['foo' => 'bar%23', '_route' => 'foo'], $matcher->match('/foo/bar%2523'));
    }

    public function testCannotRelyOnPrefix()
    {
        $coll = new RouteCollection();

        $subColl = new RouteCollection();
        $subColl->add('bar', new Route('/bar'));
        $subColl->addPrefix('/prefix');
        // overwrite the pattern, so the prefix is not valid anymore for this route in the collection
        $subColl->get('bar')->setPath('/new');

        $coll->addCollection($subColl);

        $matcher = $this->getUrlMatcher($coll);
        self::assertEquals(['_route' => 'bar'], $matcher->match('/new'));
    }

    public function testWithHost()
    {
        $coll = new RouteCollection();
        $coll->add('foo', new Route('/foo/{foo}', [], [], [], '{locale}.example.com'));

        $matcher = $this->getUrlMatcher($coll, new RequestContext('', 'GET', 'en.example.com'));
        self::assertEquals(['foo' => 'bar', '_route' => 'foo', 'locale' => 'en'], $matcher->match('/foo/bar'));
    }

    public function testWithHostOnRouteCollection()
    {
        $coll = new RouteCollection();
        $coll->add('foo', new Route('/foo/{foo}'));
        $coll->add('bar', new Route('/bar/{foo}', [], [], [], '{locale}.example.net'));
        $coll->setHost('{locale}.example.com');

        $matcher = $this->getUrlMatcher($coll, new RequestContext('', 'GET', 'en.example.com'));
        self::assertEquals(['foo' => 'bar', '_route' => 'foo', 'locale' => 'en'], $matcher->match('/foo/bar'));

        $matcher = $this->getUrlMatcher($coll, new RequestContext('', 'GET', 'en.example.com'));
        self::assertEquals(['foo' => 'bar', '_route' => 'bar', 'locale' => 'en'], $matcher->match('/bar/bar'));
    }

    public function testVariationInTrailingSlashWithHosts()
    {
        $coll = new RouteCollection();
        $coll->add('foo', new Route('/foo/', [], [], [], 'foo.example.com'));
        $coll->add('bar', new Route('/foo', [], [], [], 'bar.example.com'));

        $matcher = $this->getUrlMatcher($coll, new RequestContext('', 'GET', 'foo.example.com'));
        self::assertEquals(['_route' => 'foo'], $matcher->match('/foo/'));

        $matcher = $this->getUrlMatcher($coll, new RequestContext('', 'GET', 'bar.example.com'));
        self::assertEquals(['_route' => 'bar'], $matcher->match('/foo'));
    }

    public function testVariationInTrailingSlashWithHostsInReverse()
    {
        // The order should not matter
        $coll = new RouteCollection();
        $coll->add('bar', new Route('/foo', [], [], [], 'bar.example.com'));
        $coll->add('foo', new Route('/foo/', [], [], [], 'foo.example.com'));

        $matcher = $this->getUrlMatcher($coll, new RequestContext('', 'GET', 'foo.example.com'));
        self::assertEquals(['_route' => 'foo'], $matcher->match('/foo/'));

        $matcher = $this->getUrlMatcher($coll, new RequestContext('', 'GET', 'bar.example.com'));
        self::assertEquals(['_route' => 'bar'], $matcher->match('/foo'));
    }

    public function testVariationInTrailingSlashWithHostsAndVariable()
    {
        $coll = new RouteCollection();
        $coll->add('foo', new Route('/{foo}/', [], [], [], 'foo.example.com'));
        $coll->add('bar', new Route('/{foo}', [], [], [], 'bar.example.com'));

        $matcher = $this->getUrlMatcher($coll, new RequestContext('', 'GET', 'foo.example.com'));
        self::assertEquals(['foo' => 'bar', '_route' => 'foo'], $matcher->match('/bar/'));

        $matcher = $this->getUrlMatcher($coll, new RequestContext('', 'GET', 'bar.example.com'));
        self::assertEquals(['foo' => 'bar', '_route' => 'bar'], $matcher->match('/bar'));
    }

    public function testVariationInTrailingSlashWithHostsAndVariableInReverse()
    {
        // The order should not matter
        $coll = new RouteCollection();
        $coll->add('bar', new Route('/{foo}', [], [], [], 'bar.example.com'));
        $coll->add('foo', new Route('/{foo}/', [], [], [], 'foo.example.com'));

        $matcher = $this->getUrlMatcher($coll, new RequestContext('', 'GET', 'foo.example.com'));
        self::assertEquals(['foo' => 'bar', '_route' => 'foo'], $matcher->match('/bar/'));

        $matcher = $this->getUrlMatcher($coll, new RequestContext('', 'GET', 'bar.example.com'));
        self::assertEquals(['foo' => 'bar', '_route' => 'bar'], $matcher->match('/bar'));
    }

    public function testVariationInTrailingSlashWithMethods()
    {
        $coll = new RouteCollection();
        $coll->add('foo', new Route('/foo/', [], [], [], '', [], ['POST']));
        $coll->add('bar', new Route('/foo', [], [], [], '', [], ['GET']));

        $matcher = $this->getUrlMatcher($coll, new RequestContext('', 'POST'));
        self::assertEquals(['_route' => 'foo'], $matcher->match('/foo/'));

        $matcher = $this->getUrlMatcher($coll, new RequestContext('', 'GET'));
        self::assertEquals(['_route' => 'bar'], $matcher->match('/foo'));
    }

    public function testVariationInTrailingSlashWithMethodsInReverse()
    {
        // The order should not matter
        $coll = new RouteCollection();
        $coll->add('bar', new Route('/foo', [], [], [], '', [], ['GET']));
        $coll->add('foo', new Route('/foo/', [], [], [], '', [], ['POST']));

        $matcher = $this->getUrlMatcher($coll, new RequestContext('', 'POST'));
        self::assertEquals(['_route' => 'foo'], $matcher->match('/foo/'));

        $matcher = $this->getUrlMatcher($coll, new RequestContext('', 'GET'));
        self::assertEquals(['_route' => 'bar'], $matcher->match('/foo'));
    }

    public function testVariableVariationInTrailingSlashWithMethods()
    {
        $coll = new RouteCollection();
        $coll->add('foo', new Route('/{foo}/', [], [], [], '', [], ['POST']));
        $coll->add('bar', new Route('/{foo}', [], [], [], '', [], ['GET']));

        $matcher = $this->getUrlMatcher($coll, new RequestContext('', 'POST'));
        self::assertEquals(['foo' => 'bar', '_route' => 'foo'], $matcher->match('/bar/'));

        $matcher = $this->getUrlMatcher($coll, new RequestContext('', 'GET'));
        self::assertEquals(['foo' => 'bar', '_route' => 'bar'], $matcher->match('/bar'));
    }

    public function testVariableVariationInTrailingSlashWithMethodsInReverse()
    {
        // The order should not matter
        $coll = new RouteCollection();
        $coll->add('bar', new Route('/{foo}', [], [], [], '', [], ['GET']));
        $coll->add('foo', new Route('/{foo}/', [], [], [], '', [], ['POST']));

        $matcher = $this->getUrlMatcher($coll, new RequestContext('', 'POST'));
        self::assertEquals(['foo' => 'bar', '_route' => 'foo'], $matcher->match('/bar/'));

        $matcher = $this->getUrlMatcher($coll, new RequestContext('', 'GET'));
        self::assertEquals(['foo' => 'bar', '_route' => 'bar'], $matcher->match('/bar'));
    }

    public function testMixOfStaticAndVariableVariationInTrailingSlashWithHosts()
    {
        $coll = new RouteCollection();
        $coll->add('foo', new Route('/foo/', [], [], [], 'foo.example.com'));
        $coll->add('bar', new Route('/{foo}', [], [], [], 'bar.example.com'));

        $matcher = $this->getUrlMatcher($coll, new RequestContext('', 'GET', 'foo.example.com'));
        self::assertEquals(['_route' => 'foo'], $matcher->match('/foo/'));

        $matcher = $this->getUrlMatcher($coll, new RequestContext('', 'GET', 'bar.example.com'));
        self::assertEquals(['foo' => 'bar', '_route' => 'bar'], $matcher->match('/bar'));
    }

    public function testMixOfStaticAndVariableVariationInTrailingSlashWithMethods()
    {
        $coll = new RouteCollection();
        $coll->add('foo', new Route('/foo/', [], [], [], '', [], ['POST']));
        $coll->add('bar', new Route('/{foo}', [], [], [], '', [], ['GET']));

        $matcher = $this->getUrlMatcher($coll, new RequestContext('', 'POST'));
        self::assertEquals(['_route' => 'foo'], $matcher->match('/foo/'));

        $matcher = $this->getUrlMatcher($coll, new RequestContext('', 'GET'));
        self::assertEquals(['foo' => 'bar', '_route' => 'bar'], $matcher->match('/bar'));
        self::assertEquals(['foo' => 'foo', '_route' => 'bar'], $matcher->match('/foo'));
    }

    public function testWithOutHostHostDoesNotMatch()
    {
        self::expectException(ResourceNotFoundException::class);
        $coll = new RouteCollection();
        $coll->add('foo', new Route('/foo/{foo}', [], [], [], '{locale}.example.com'));

        $matcher = $this->getUrlMatcher($coll, new RequestContext('', 'GET', 'example.com'));
        $matcher->match('/foo/bar');
    }

    public function testPathIsCaseSensitive()
    {
        self::expectException(ResourceNotFoundException::class);
        $coll = new RouteCollection();
        $coll->add('foo', new Route('/locale', [], ['locale' => 'EN|FR|DE']));

        $matcher = $this->getUrlMatcher($coll);
        $matcher->match('/en');
    }

    public function testHostIsCaseInsensitive()
    {
        $coll = new RouteCollection();
        $coll->add('foo', new Route('/', [], ['locale' => 'EN|FR|DE'], [], '{locale}.example.com'));

        $matcher = $this->getUrlMatcher($coll, new RequestContext('', 'GET', 'en.example.com'));
        self::assertEquals(['_route' => 'foo', 'locale' => 'en'], $matcher->match('/'));
    }

    public function testNoConfiguration()
    {
        self::expectException(NoConfigurationException::class);
        $coll = new RouteCollection();

        $matcher = $this->getUrlMatcher($coll);
        $matcher->match('/');
    }

    public function testNestedCollections()
    {
        $coll = new RouteCollection();

        $subColl = new RouteCollection();
        $subColl->add('a', new Route('/a'));
        $subColl->add('b', new Route('/b'));
        $subColl->add('c', new Route('/c'));
        $subColl->addPrefix('/p');
        $coll->addCollection($subColl);

        $coll->add('baz', new Route('/{baz}'));

        $subColl = new RouteCollection();
        $subColl->add('buz', new Route('/buz'));
        $subColl->addPrefix('/prefix');
        $coll->addCollection($subColl);

        $matcher = $this->getUrlMatcher($coll);
        self::assertEquals(['_route' => 'a'], $matcher->match('/p/a'));
        self::assertEquals(['_route' => 'baz', 'baz' => 'p'], $matcher->match('/p'));
        self::assertEquals(['_route' => 'buz'], $matcher->match('/prefix/buz'));
    }

    public function testSchemeAndMethodMismatch()
    {
        self::expectException(ResourceNotFoundException::class);
        self::expectExceptionMessage('No routes found for "/".');
        $coll = new RouteCollection();
        $coll->add('foo', new Route('/', [], [], [], null, ['https'], ['POST']));

        $matcher = $this->getUrlMatcher($coll);
        $matcher->match('/');
    }

    public function testSiblingRoutes()
    {
        $coll = new RouteCollection();
        $coll->add('a', (new Route('/a{a}'))->setMethods('POST'));
        $coll->add('b', (new Route('/a{a}'))->setMethods('PUT'));
        $coll->add('c', new Route('/a{a}'));
        $coll->add('d', (new Route('/b{a}'))->setCondition('false'));
        $coll->add('e', (new Route('/{b}{a}'))->setCondition('false'));
        $coll->add('f', (new Route('/{b}{a}'))->setRequirements(['b' => 'b']));

        $matcher = $this->getUrlMatcher($coll);
        self::assertEquals(['_route' => 'c', 'a' => 'a'], $matcher->match('/aa'));
        self::assertEquals(['_route' => 'f', 'b' => 'b', 'a' => 'a'], $matcher->match('/ba'));
    }

    public function testUnicodeRoute()
    {
        $coll = new RouteCollection();
        $coll->add('a', new Route('/{a}', [], ['a' => '.'], ['utf8' => false]));
        $coll->add('b', new Route('/{a}', [], ['a' => '.'], ['utf8' => true]));

        $matcher = $this->getUrlMatcher($coll);
        self::assertEquals(['_route' => 'b', 'a' => 'é'], $matcher->match('/é'));
    }

    public function testRequirementWithCapturingGroup()
    {
        $coll = new RouteCollection();
        $coll->add('a', new Route('/{a}/{b}', [], ['a' => '(a|b)']));

        $matcher = $this->getUrlMatcher($coll);
        self::assertEquals(['_route' => 'a', 'a' => 'a', 'b' => 'b'], $matcher->match('/a/b'));
    }

    public function testDotAllWithCatchAll()
    {
        $coll = new RouteCollection();
        $coll->add('a', new Route('/{id}.html', [], ['id' => '.+']));
        $coll->add('b', new Route('/{all}', [], ['all' => '.+']));

        $matcher = $this->getUrlMatcher($coll);
        self::assertEquals(['_route' => 'a', 'id' => 'foo/bar'], $matcher->match('/foo/bar.html'));
    }

    public function testHostPattern()
    {
        $coll = new RouteCollection();
        $coll->add('a', new Route('/{app}/{action}/{unused}', [], [], [], '{host}'));

        $expected = [
            '_route' => 'a',
            'app' => 'an_app',
            'action' => 'an_action',
            'unused' => 'unused',
            'host' => 'foo',
        ];
        $matcher = $this->getUrlMatcher($coll, new RequestContext('', 'GET', 'foo'));
        self::assertEquals($expected, $matcher->match('/an_app/an_action/unused'));
    }

    public function testUtf8Prefix()
    {
        $coll = new RouteCollection();
        $coll->add('a', new Route('/é{foo}', [], [], ['utf8' => true]));
        $coll->add('b', new Route('/è{bar}', [], [], ['utf8' => true]));

        $matcher = $this->getUrlMatcher($coll);
        self::assertEquals('a', $matcher->match('/éo')['_route']);
    }

    public function testUtf8AndMethodMatching()
    {
        $coll = new RouteCollection();
        $coll->add('a', new Route('/admin/api/list/{shortClassName}/{id}.{_format}', [], [], ['utf8' => true], '', [], ['PUT']));
        $coll->add('b', new Route('/admin/api/package.{_format}', [], [], [], '', [], ['POST']));
        $coll->add('c', new Route('/admin/api/package.{_format}', ['_format' => 'json'], [], [], '', [], ['GET']));

        $matcher = $this->getUrlMatcher($coll);
        self::assertEquals('c', $matcher->match('/admin/api/package.json')['_route']);
    }

    public function testHostWithDot()
    {
        $coll = new RouteCollection();
        $coll->add('a', new Route('/foo', [], [], [], 'foo.example.com'));
        $coll->add('b', new Route('/bar/{baz}'));

        $matcher = $this->getUrlMatcher($coll);
        self::assertEquals('b', $matcher->match('/bar/abc.123')['_route']);
    }

    public function testSlashVariant()
    {
        $coll = new RouteCollection();
        $coll->add('a', new Route('/foo/{bar}', [], ['bar' => '.*']));

        $matcher = $this->getUrlMatcher($coll);
        self::assertEquals('a', $matcher->match('/foo/')['_route']);
    }

    public function testSlashVariant2()
    {
        $coll = new RouteCollection();
        $coll->add('a', new Route('/foo/{bar}/', [], ['bar' => '.*']));

        $matcher = $this->getUrlMatcher($coll);
        self::assertEquals(['_route' => 'a', 'bar' => 'bar'], $matcher->match('/foo/bar/'));
    }

    public function testSlashWithVerb()
    {
        $coll = new RouteCollection();
        $coll->add('a', new Route('/{foo}', [], [], [], '', [], ['put', 'delete']));
        $coll->add('b', new Route('/bar/'));

        $matcher = $this->getUrlMatcher($coll);
        self::assertSame(['_route' => 'b'], $matcher->match('/bar/'));

        $coll = new RouteCollection();
        $coll->add('a', new Route('/dav/{foo}', [], ['foo' => '.*'], [], '', [], ['GET', 'OPTIONS']));

        $matcher = $this->getUrlMatcher($coll, new RequestContext('', 'OPTIONS'));
        $expected = [
            '_route' => 'a',
            'foo' => 'files/bar/',
        ];
        self::assertEquals($expected, $matcher->match('/dav/files/bar/'));
    }

    public function testSlashAndVerbPrecedence()
    {
        $coll = new RouteCollection();
        $coll->add('a', new Route('/api/customers/{customerId}/contactpersons/', [], [], [], '', [], ['post']));
        $coll->add('b', new Route('/api/customers/{customerId}/contactpersons', [], [], [], '', [], ['get']));

        $matcher = $this->getUrlMatcher($coll);
        $expected = [
            '_route' => 'b',
            'customerId' => '123',
        ];
        self::assertEquals($expected, $matcher->match('/api/customers/123/contactpersons'));

        $coll = new RouteCollection();
        $coll->add('a', new Route('/api/customers/{customerId}/contactpersons/', [], [], [], '', [], ['get']));
        $coll->add('b', new Route('/api/customers/{customerId}/contactpersons', [], [], [], '', [], ['post']));

        $matcher = $this->getUrlMatcher($coll, new RequestContext('', 'POST'));
        $expected = [
            '_route' => 'b',
            'customerId' => '123',
        ];
        self::assertEquals($expected, $matcher->match('/api/customers/123/contactpersons'));
    }

    public function testGreedyTrailingRequirement()
    {
        $coll = new RouteCollection();
        $coll->add('a', new Route('/{a}', [], ['a' => '.+']));

        $matcher = $this->getUrlMatcher($coll);

        self::assertEquals(['_route' => 'a', 'a' => 'foo'], $matcher->match('/foo'));
        self::assertEquals(['_route' => 'a', 'a' => 'foo/'], $matcher->match('/foo/'));
    }

    public function testTrailingRequirementWithDefault()
    {
        $coll = new RouteCollection();
        $coll->add('a', new Route('/fr-fr/{a}', ['a' => 'aaa'], ['a' => '.+']));
        $coll->add('b', new Route('/en-en/{b}', ['b' => 'bbb'], ['b' => '.*']));

        $matcher = $this->getUrlMatcher($coll);

        self::assertEquals(['_route' => 'a', 'a' => 'aaa'], $matcher->match('/fr-fr'));
        self::assertEquals(['_route' => 'a', 'a' => 'AAA'], $matcher->match('/fr-fr/AAA'));
        self::assertEquals(['_route' => 'b', 'b' => 'bbb'], $matcher->match('/en-en'));
        self::assertEquals(['_route' => 'b', 'b' => 'BBB'], $matcher->match('/en-en/BBB'));
    }

    public function testTrailingRequirementWithDefaultA()
    {
        $coll = new RouteCollection();
        $coll->add('a', new Route('/fr-fr/{a}', ['a' => 'aaa'], ['a' => '.+']));

        $matcher = $this->getUrlMatcher($coll);

        self::expectException(ResourceNotFoundException::class);
        $matcher->match('/fr-fr/');
    }

    public function testTrailingRequirementWithDefaultB()
    {
        $coll = new RouteCollection();
        $coll->add('b', new Route('/en-en/{b}', ['b' => 'bbb'], ['b' => '.*']));

        $matcher = $this->getUrlMatcher($coll);

        self::assertEquals(['_route' => 'b', 'b' => ''], $matcher->match('/en-en/'));
    }

    public function testRestrictiveTrailingRequirementWithStaticRouteAfter()
    {
        $coll = new RouteCollection();
        $coll->add('a', new Route('/hello{_}', [], ['_' => '/(?!/)']));
        $coll->add('b', new Route('/hello'));

        $matcher = $this->getUrlMatcher($coll);

        self::assertEquals(['_route' => 'a', '_' => '/'], $matcher->match('/hello/'));
    }

    protected function getUrlMatcher(RouteCollection $routes, RequestContext $context = null)
    {
        return new UrlMatcher($routes, $context ?? new RequestContext());
    }
}
