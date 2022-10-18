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
        $this->assertIsArray($matcher->match('/foo'));
    }

    public function testMethodNotAllowed()
    {
        $coll = new RouteCollection();
        $coll->add('foo', new Route('/foo', [], [], [], '', [], ['post']));

        $matcher = $this->getUrlMatcher($coll);

        try {
            $matcher->match('/foo');
            $this->fail();
        } catch (MethodNotAllowedException $e) {
            $this->assertEquals(['POST'], $e->getAllowedMethods());
        }
    }

    public function testMethodNotAllowedOnRoot()
    {
        $coll = new RouteCollection();
        $coll->add('foo', new Route('/', [], [], [], '', [], ['GET']));

        $matcher = $this->getUrlMatcher($coll, new RequestContext('', 'POST'));

        try {
            $matcher->match('/');
            $this->fail();
        } catch (MethodNotAllowedException $e) {
            $this->assertEquals(['GET'], $e->getAllowedMethods());
        }
    }

    public function testHeadAllowedWhenRequirementContainsGet()
    {
        $coll = new RouteCollection();
        $coll->add('foo', new Route('/foo', [], [], [], '', [], ['get']));

        $matcher = $this->getUrlMatcher($coll, new RequestContext('', 'head'));
        $this->assertIsArray($matcher->match('/foo'));
    }

    public function testMethodNotAllowedAggregatesAllowedMethods()
    {
        $coll = new RouteCollection();
        $coll->add('foo1', new Route('/foo', [], [], [], '', [], ['post']));
        $coll->add('foo2', new Route('/foo', [], [], [], '', [], ['put', 'delete']));

        $matcher = $this->getUrlMatcher($coll);

        try {
            $matcher->match('/foo');
            $this->fail();
        } catch (MethodNotAllowedException $e) {
            $this->assertEquals(['POST', 'PUT', 'DELETE'], $e->getAllowedMethods());
        }
    }

    public function testPatternMatchAndParameterReturn()
    {
        $collection = new RouteCollection();
        $collection->add('foo', new Route('/foo/{bar}'));
        $matcher = $this->getUrlMatcher($collection);
        try {
            $matcher->match('/no-match');
            $this->fail();
        } catch (ResourceNotFoundException $e) {
        }

        $this->assertEquals(['_route' => 'foo', 'bar' => 'baz'], $matcher->match('/foo/baz'));
    }

    public function testDefaultsAreMerged()
    {
        // test that defaults are merged
        $collection = new RouteCollection();
        $collection->add('foo', new Route('/foo/{bar}', ['def' => 'test']));
        $matcher = $this->getUrlMatcher($collection);
        $this->assertEquals(['_route' => 'foo', 'bar' => 'baz', 'def' => 'test'], $matcher->match('/foo/baz'));
    }

    public function testMethodIsIgnoredIfNoMethodGiven()
    {
        // test that route "method" is ignored if no method is given in the context
        $collection = new RouteCollection();
        $collection->add('foo', new Route('/foo', [], [], [], '', [], ['get', 'head']));
        $matcher = $this->getUrlMatcher($collection);
        $this->assertIsArray($matcher->match('/foo'));

        // route does not match with POST method context
        $matcher = $this->getUrlMatcher($collection, new RequestContext('', 'post'));
        try {
            $matcher->match('/foo');
            $this->fail();
        } catch (MethodNotAllowedException $e) {
        }

        // route does match with GET or HEAD method context
        $matcher = $this->getUrlMatcher($collection);
        $this->assertIsArray($matcher->match('/foo'));
        $matcher = $this->getUrlMatcher($collection, new RequestContext('', 'head'));
        $this->assertIsArray($matcher->match('/foo'));
    }

    public function testRouteWithOptionalVariableAsFirstSegment()
    {
        $collection = new RouteCollection();
        $collection->add('bar', new Route('/{bar}/foo', ['bar' => 'bar'], ['bar' => 'foo|bar']));
        $matcher = $this->getUrlMatcher($collection);
        $this->assertEquals(['_route' => 'bar', 'bar' => 'bar'], $matcher->match('/bar/foo'));
        $this->assertEquals(['_route' => 'bar', 'bar' => 'foo'], $matcher->match('/foo/foo'));

        $collection = new RouteCollection();
        $collection->add('bar', new Route('/{bar}', ['bar' => 'bar'], ['bar' => 'foo|bar']));
        $matcher = $this->getUrlMatcher($collection);
        $this->assertEquals(['_route' => 'bar', 'bar' => 'foo'], $matcher->match('/foo'));
        $this->assertEquals(['_route' => 'bar', 'bar' => 'bar'], $matcher->match('/'));
    }

    public function testRouteWithOnlyOptionalVariables()
    {
        $collection = new RouteCollection();
        $collection->add('bar', new Route('/{foo}/{bar}', ['foo' => 'foo', 'bar' => 'bar'], []));
        $matcher = $this->getUrlMatcher($collection);
        $this->assertEquals(['_route' => 'bar', 'foo' => 'foo', 'bar' => 'bar'], $matcher->match('/'));
        $this->assertEquals(['_route' => 'bar', 'foo' => 'a', 'bar' => 'bar'], $matcher->match('/a'));
        $this->assertEquals(['_route' => 'bar', 'foo' => 'a', 'bar' => 'b'], $matcher->match('/a/b'));
    }

    public function testMatchWithPrefixes()
    {
        $collection = new RouteCollection();
        $collection->add('foo', new Route('/{foo}'));
        $collection->addPrefix('/b');
        $collection->addPrefix('/a');

        $matcher = $this->getUrlMatcher($collection);
        $this->assertEquals(['_route' => 'foo', 'foo' => 'foo'], $matcher->match('/a/b/foo'));
    }

    public function testMatchWithDynamicPrefix()
    {
        $collection = new RouteCollection();
        $collection->add('foo', new Route('/{foo}'));
        $collection->addPrefix('/b');
        $collection->addPrefix('/{_locale}');

        $matcher = $this->getUrlMatcher($collection);
        $this->assertEquals(['_locale' => 'fr', '_route' => 'foo', 'foo' => 'foo'], $matcher->match('/fr/b/foo'));
    }

    public function testMatchSpecialRouteName()
    {
        $collection = new RouteCollection();
        $collection->add('$péß^a|', new Route('/bar'));

        $matcher = $this->getUrlMatcher($collection);
        $this->assertEquals(['_route' => '$péß^a|'], $matcher->match('/bar'));
    }

    public function testMatchImportantVariable()
    {
        $collection = new RouteCollection();
        $collection->add('index', new Route('/index.{!_format}', ['_format' => 'xml']));

        $matcher = $this->getUrlMatcher($collection);
        $this->assertEquals(['_route' => 'index', '_format' => 'xml'], $matcher->match('/index.xml'));
    }

    public function testShortPathDoesNotMatchImportantVariable()
    {
        $this->expectException(ResourceNotFoundException::class);

        $collection = new RouteCollection();
        $collection->add('index', new Route('/index.{!_format}', ['_format' => 'xml']));

        $this->getUrlMatcher($collection)->match('/index');
    }

    public function testTrailingEncodedNewlineIsNotOverlooked()
    {
        $this->expectException(ResourceNotFoundException::class);
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
        $this->assertEquals(['_route' => 'foo', 'foo' => $chars], $matcher->match('/'.rawurlencode($chars).'/bar'));
        $this->assertEquals(['_route' => 'foo', 'foo' => $chars], $matcher->match('/'.strtr($chars, ['%' => '%25']).'/bar'));
    }

    public function testMatchWithDotMetacharacterInRequirements()
    {
        $collection = new RouteCollection();
        $collection->add('foo', new Route('/{foo}/bar', [], ['foo' => '.+']));

        $matcher = $this->getUrlMatcher($collection);
        $this->assertEquals(['_route' => 'foo', 'foo' => "\n"], $matcher->match('/'.urlencode("\n").'/bar'), 'linefeed character is matched');
    }

    public function testMatchOverriddenRoute()
    {
        $collection = new RouteCollection();
        $collection->add('foo', new Route('/foo'));

        $collection1 = new RouteCollection();
        $collection1->add('foo', new Route('/foo1'));

        $collection->addCollection($collection1);

        $matcher = $this->getUrlMatcher($collection);

        $this->assertEquals(['_route' => 'foo'], $matcher->match('/foo1'));
        $this->expectException(ResourceNotFoundException::class);
        $this->assertEquals([], $matcher->match('/foo'));
    }

    public function testMatchRegression()
    {
        $coll = new RouteCollection();
        $coll->add('foo', new Route('/foo/{foo}'));
        $coll->add('bar', new Route('/foo/bar/{foo}'));

        $matcher = $this->getUrlMatcher($coll);
        $this->assertEquals(['foo' => 'bar', '_route' => 'bar'], $matcher->match('/foo/bar/bar'));

        $collection = new RouteCollection();
        $collection->add('foo', new Route('/{bar}'));
        $matcher = $this->getUrlMatcher($collection);
        try {
            $matcher->match('/');
            $this->fail();
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

        $this->assertEquals('foo2', $route);
    }

    public function testDefaultRequirementForOptionalVariables()
    {
        $coll = new RouteCollection();
        $coll->add('test', new Route('/{page}.{_format}', ['page' => 'index', '_format' => 'html']));

        $matcher = $this->getUrlMatcher($coll);
        $this->assertEquals(['page' => 'my-page', '_format' => 'xml', '_route' => 'test'], $matcher->match('/my-page.xml'));
    }

    public function testMatchingIsEager()
    {
        $coll = new RouteCollection();
        $coll->add('test', new Route('/{foo}-{bar}-', [], ['foo' => '.+', 'bar' => '.+']));

        $matcher = $this->getUrlMatcher($coll);
        $this->assertEquals(['foo' => 'text1-text2-text3', 'bar' => 'text4', '_route' => 'test'], $matcher->match('/text1-text2-text3-text4-'));
    }

    public function testAdjacentVariables()
    {
        $coll = new RouteCollection();
        $coll->add('test', new Route('/{w}{x}{y}{z}.{_format}', ['z' => 'default-z', '_format' => 'html'], ['y' => 'y|Y']));

        $matcher = $this->getUrlMatcher($coll);
        // 'w' eagerly matches as much as possible and the other variables match the remaining chars.
        // This also shows that the variables w-z must all exclude the separating char (the dot '.' in this case) by default requirement.
        // Otherwise they would also consume '.xml' and _format would never match as it's an optional variable.
        $this->assertEquals(['w' => 'wwwww', 'x' => 'x', 'y' => 'Y', 'z' => 'Z', '_format' => 'xml', '_route' => 'test'], $matcher->match('/wwwwwxYZ.xml'));
        // As 'y' has custom requirement and can only be of value 'y|Y', it will leave  'ZZZ' to variable z.
        // So with carefully chosen requirements adjacent variables, can be useful.
        $this->assertEquals(['w' => 'wwwww', 'x' => 'x', 'y' => 'y', 'z' => 'ZZZ', '_format' => 'html', '_route' => 'test'], $matcher->match('/wwwwwxyZZZ'));
        // z and _format are optional.
        $this->assertEquals(['w' => 'wwwww', 'x' => 'x', 'y' => 'y', 'z' => 'default-z', '_format' => 'html', '_route' => 'test'], $matcher->match('/wwwwwxy'));

        $this->expectException(ResourceNotFoundException::class);
        $matcher->match('/wxy.html');
    }

    public function testOptionalVariableWithNoRealSeparator()
    {
        $coll = new RouteCollection();
        $coll->add('test', new Route('/get{what}', ['what' => 'All']));
        $matcher = $this->getUrlMatcher($coll);

        $this->assertEquals(['what' => 'All', '_route' => 'test'], $matcher->match('/get'));
        $this->assertEquals(['what' => 'Sites', '_route' => 'test'], $matcher->match('/getSites'));

        // Usually the character in front of an optional parameter can be left out, e.g. with pattern '/get/{what}' just '/get' would match.
        // But here the 't' in 'get' is not a separating character, so it makes no sense to match without it.
        $this->expectException(ResourceNotFoundException::class);
        $matcher->match('/ge');
    }

    public function testRequiredVariableWithNoRealSeparator()
    {
        $coll = new RouteCollection();
        $coll->add('test', new Route('/get{what}Suffix'));
        $matcher = $this->getUrlMatcher($coll);

        $this->assertEquals(['what' => 'Sites', '_route' => 'test'], $matcher->match('/getSitesSuffix'));
    }

    public function testDefaultRequirementOfVariable()
    {
        $coll = new RouteCollection();
        $coll->add('test', new Route('/{page}.{_format}'));
        $matcher = $this->getUrlMatcher($coll);

        $this->assertEquals(['page' => 'index', '_format' => 'mobile.html', '_route' => 'test'], $matcher->match('/index.mobile.html'));
    }

    public function testDefaultRequirementOfVariableDisallowsSlash()
    {
        $this->expectException(ResourceNotFoundException::class);
        $coll = new RouteCollection();
        $coll->add('test', new Route('/{page}.{_format}'));
        $matcher = $this->getUrlMatcher($coll);

        $matcher->match('/index.sl/ash');
    }

    public function testDefaultRequirementOfVariableDisallowsNextSeparator()
    {
        $this->expectException(ResourceNotFoundException::class);
        $coll = new RouteCollection();
        $coll->add('test', new Route('/{page}.{_format}', [], ['_format' => 'html|xml']));
        $matcher = $this->getUrlMatcher($coll);

        $matcher->match('/do.t.html');
    }

    public function testMissingTrailingSlash()
    {
        $this->expectException(ResourceNotFoundException::class);
        $coll = new RouteCollection();
        $coll->add('foo', new Route('/foo/'));

        $matcher = $this->getUrlMatcher($coll);
        $matcher->match('/foo');
    }

    public function testExtraTrailingSlash()
    {
        $this->getExpectedException() ?: $this->expectException(ResourceNotFoundException::class);
        $coll = new RouteCollection();
        $coll->add('foo', new Route('/foo'));

        $matcher = $this->getUrlMatcher($coll);
        $matcher->match('/foo/');
    }

    public function testMissingTrailingSlashForNonSafeMethod()
    {
        $this->getExpectedException() ?: $this->expectException(ResourceNotFoundException::class);
        $coll = new RouteCollection();
        $coll->add('foo', new Route('/foo/'));

        $context = new RequestContext();
        $context->setMethod('POST');
        $matcher = $this->getUrlMatcher($coll, $context);
        $matcher->match('/foo');
    }

    public function testExtraTrailingSlashForNonSafeMethod()
    {
        $this->getExpectedException() ?: $this->expectException(ResourceNotFoundException::class);
        $coll = new RouteCollection();
        $coll->add('foo', new Route('/foo'));

        $context = new RequestContext();
        $context->setMethod('POST');
        $matcher = $this->getUrlMatcher($coll, $context);
        $matcher->match('/foo/');
    }

    public function testSchemeRequirement()
    {
        $this->getExpectedException() ?: $this->expectException(ResourceNotFoundException::class);
        $coll = new RouteCollection();
        $coll->add('foo', new Route('/foo', [], [], [], '', ['https']));
        $matcher = $this->getUrlMatcher($coll);
        $matcher->match('/foo');
    }

    public function testSchemeRequirementForNonSafeMethod()
    {
        $this->getExpectedException() ?: $this->expectException(ResourceNotFoundException::class);
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
        $this->assertEquals(['_route' => 'http_route'], $matcher->match('/'));
    }

    public function testCondition()
    {
        $this->expectException(ResourceNotFoundException::class);
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
        $this->assertEquals(['bar' => 'bar', '_route' => 'foo'], $matcher->match('/foo/bar'));
    }

    public function testRouteParametersCondition()
    {
        $coll = new RouteCollection();
        $route = new Route('/foo');
        $route->setCondition("params['_route'] matches '/^s[a-z]+$/'");
        $coll->add('static', $route);
        $route = new Route('/bar');
        $route->setHost('en.example.com');
        $route->setCondition("params['_route'] matches '/^s[a-z\-]+$/'");
        $coll->add('static-with-host', $route);
        $route = new Route('/foo/{id}');
        $route->setCondition("params['id'] < 100");
        $coll->add('dynamic1', $route);
        $route = new Route('/foo/{id}');
        $route->setCondition("params['id'] > 100 and params['id'] < 1000");
        $coll->add('dynamic2', $route);
        $route = new Route('/bar/{id}/');
        $route->setCondition("params['id'] < 100");
        $coll->add('dynamic-with-slash', $route);
        $matcher = $this->getUrlMatcher($coll, new RequestContext('/sub/front.php', 'GET', 'en.example.com'));

        $this->assertEquals(['_route' => 'static'], $matcher->match('/foo'));
        $this->assertEquals(['_route' => 'static-with-host'], $matcher->match('/bar'));
        $this->assertEquals(['_route' => 'dynamic1', 'id' => '10'], $matcher->match('/foo/10'));
        $this->assertEquals(['_route' => 'dynamic2', 'id' => '200'], $matcher->match('/foo/200'));
        $this->assertEquals(['_route' => 'dynamic-with-slash', 'id' => '10'], $matcher->match('/bar/10/'));

        $this->expectException(ResourceNotFoundException::class);
        $matcher->match('/foo/3000');
    }

    public function testDecodeOnce()
    {
        $coll = new RouteCollection();
        $coll->add('foo', new Route('/foo/{foo}'));

        $matcher = $this->getUrlMatcher($coll);
        $this->assertEquals(['foo' => 'bar%23', '_route' => 'foo'], $matcher->match('/foo/bar%2523'));
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
        $this->assertEquals(['_route' => 'bar'], $matcher->match('/new'));
    }

    public function testWithHost()
    {
        $coll = new RouteCollection();
        $coll->add('foo', new Route('/foo/{foo}', [], [], [], '{locale}.example.com'));

        $matcher = $this->getUrlMatcher($coll, new RequestContext('', 'GET', 'en.example.com'));
        $this->assertEquals(['foo' => 'bar', '_route' => 'foo', 'locale' => 'en'], $matcher->match('/foo/bar'));
    }

    public function testWithHostOnRouteCollection()
    {
        $coll = new RouteCollection();
        $coll->add('foo', new Route('/foo/{foo}'));
        $coll->add('bar', new Route('/bar/{foo}', [], [], [], '{locale}.example.net'));
        $coll->setHost('{locale}.example.com');

        $matcher = $this->getUrlMatcher($coll, new RequestContext('', 'GET', 'en.example.com'));
        $this->assertEquals(['foo' => 'bar', '_route' => 'foo', 'locale' => 'en'], $matcher->match('/foo/bar'));

        $matcher = $this->getUrlMatcher($coll, new RequestContext('', 'GET', 'en.example.com'));
        $this->assertEquals(['foo' => 'bar', '_route' => 'bar', 'locale' => 'en'], $matcher->match('/bar/bar'));
    }

    public function testVariationInTrailingSlashWithHosts()
    {
        $coll = new RouteCollection();
        $coll->add('foo', new Route('/foo/', [], [], [], 'foo.example.com'));
        $coll->add('bar', new Route('/foo', [], [], [], 'bar.example.com'));

        $matcher = $this->getUrlMatcher($coll, new RequestContext('', 'GET', 'foo.example.com'));
        $this->assertEquals(['_route' => 'foo'], $matcher->match('/foo/'));

        $matcher = $this->getUrlMatcher($coll, new RequestContext('', 'GET', 'bar.example.com'));
        $this->assertEquals(['_route' => 'bar'], $matcher->match('/foo'));
    }

    public function testVariationInTrailingSlashWithHostsInReverse()
    {
        // The order should not matter
        $coll = new RouteCollection();
        $coll->add('bar', new Route('/foo', [], [], [], 'bar.example.com'));
        $coll->add('foo', new Route('/foo/', [], [], [], 'foo.example.com'));

        $matcher = $this->getUrlMatcher($coll, new RequestContext('', 'GET', 'foo.example.com'));
        $this->assertEquals(['_route' => 'foo'], $matcher->match('/foo/'));

        $matcher = $this->getUrlMatcher($coll, new RequestContext('', 'GET', 'bar.example.com'));
        $this->assertEquals(['_route' => 'bar'], $matcher->match('/foo'));
    }

    public function testVariationInTrailingSlashWithHostsAndVariable()
    {
        $coll = new RouteCollection();
        $coll->add('foo', new Route('/{foo}/', [], [], [], 'foo.example.com'));
        $coll->add('bar', new Route('/{foo}', [], [], [], 'bar.example.com'));

        $matcher = $this->getUrlMatcher($coll, new RequestContext('', 'GET', 'foo.example.com'));
        $this->assertEquals(['foo' => 'bar', '_route' => 'foo'], $matcher->match('/bar/'));

        $matcher = $this->getUrlMatcher($coll, new RequestContext('', 'GET', 'bar.example.com'));
        $this->assertEquals(['foo' => 'bar', '_route' => 'bar'], $matcher->match('/bar'));
    }

    public function testVariationInTrailingSlashWithHostsAndVariableInReverse()
    {
        // The order should not matter
        $coll = new RouteCollection();
        $coll->add('bar', new Route('/{foo}', [], [], [], 'bar.example.com'));
        $coll->add('foo', new Route('/{foo}/', [], [], [], 'foo.example.com'));

        $matcher = $this->getUrlMatcher($coll, new RequestContext('', 'GET', 'foo.example.com'));
        $this->assertEquals(['foo' => 'bar', '_route' => 'foo'], $matcher->match('/bar/'));

        $matcher = $this->getUrlMatcher($coll, new RequestContext('', 'GET', 'bar.example.com'));
        $this->assertEquals(['foo' => 'bar', '_route' => 'bar'], $matcher->match('/bar'));
    }

    public function testVariationInTrailingSlashWithMethods()
    {
        $coll = new RouteCollection();
        $coll->add('foo', new Route('/foo/', [], [], [], '', [], ['POST']));
        $coll->add('bar', new Route('/foo', [], [], [], '', [], ['GET']));

        $matcher = $this->getUrlMatcher($coll, new RequestContext('', 'POST'));
        $this->assertEquals(['_route' => 'foo'], $matcher->match('/foo/'));

        $matcher = $this->getUrlMatcher($coll, new RequestContext('', 'GET'));
        $this->assertEquals(['_route' => 'bar'], $matcher->match('/foo'));
    }

    public function testVariationInTrailingSlashWithMethodsInReverse()
    {
        // The order should not matter
        $coll = new RouteCollection();
        $coll->add('bar', new Route('/foo', [], [], [], '', [], ['GET']));
        $coll->add('foo', new Route('/foo/', [], [], [], '', [], ['POST']));

        $matcher = $this->getUrlMatcher($coll, new RequestContext('', 'POST'));
        $this->assertEquals(['_route' => 'foo'], $matcher->match('/foo/'));

        $matcher = $this->getUrlMatcher($coll, new RequestContext('', 'GET'));
        $this->assertEquals(['_route' => 'bar'], $matcher->match('/foo'));
    }

    public function testVariableVariationInTrailingSlashWithMethods()
    {
        $coll = new RouteCollection();
        $coll->add('foo', new Route('/{foo}/', [], [], [], '', [], ['POST']));
        $coll->add('bar', new Route('/{foo}', [], [], [], '', [], ['GET']));

        $matcher = $this->getUrlMatcher($coll, new RequestContext('', 'POST'));
        $this->assertEquals(['foo' => 'bar', '_route' => 'foo'], $matcher->match('/bar/'));

        $matcher = $this->getUrlMatcher($coll, new RequestContext('', 'GET'));
        $this->assertEquals(['foo' => 'bar', '_route' => 'bar'], $matcher->match('/bar'));
    }

    public function testVariableVariationInTrailingSlashWithMethodsInReverse()
    {
        // The order should not matter
        $coll = new RouteCollection();
        $coll->add('bar', new Route('/{foo}', [], [], [], '', [], ['GET']));
        $coll->add('foo', new Route('/{foo}/', [], [], [], '', [], ['POST']));

        $matcher = $this->getUrlMatcher($coll, new RequestContext('', 'POST'));
        $this->assertEquals(['foo' => 'bar', '_route' => 'foo'], $matcher->match('/bar/'));

        $matcher = $this->getUrlMatcher($coll, new RequestContext('', 'GET'));
        $this->assertEquals(['foo' => 'bar', '_route' => 'bar'], $matcher->match('/bar'));
    }

    public function testMixOfStaticAndVariableVariationInTrailingSlashWithHosts()
    {
        $coll = new RouteCollection();
        $coll->add('foo', new Route('/foo/', [], [], [], 'foo.example.com'));
        $coll->add('bar', new Route('/{foo}', [], [], [], 'bar.example.com'));

        $matcher = $this->getUrlMatcher($coll, new RequestContext('', 'GET', 'foo.example.com'));
        $this->assertEquals(['_route' => 'foo'], $matcher->match('/foo/'));

        $matcher = $this->getUrlMatcher($coll, new RequestContext('', 'GET', 'bar.example.com'));
        $this->assertEquals(['foo' => 'bar', '_route' => 'bar'], $matcher->match('/bar'));
    }

    public function testMixOfStaticAndVariableVariationInTrailingSlashWithMethods()
    {
        $coll = new RouteCollection();
        $coll->add('foo', new Route('/foo/', [], [], [], '', [], ['POST']));
        $coll->add('bar', new Route('/{foo}', [], [], [], '', [], ['GET']));

        $matcher = $this->getUrlMatcher($coll, new RequestContext('', 'POST'));
        $this->assertEquals(['_route' => 'foo'], $matcher->match('/foo/'));

        $matcher = $this->getUrlMatcher($coll, new RequestContext('', 'GET'));
        $this->assertEquals(['foo' => 'bar', '_route' => 'bar'], $matcher->match('/bar'));
        $this->assertEquals(['foo' => 'foo', '_route' => 'bar'], $matcher->match('/foo'));
    }

    public function testWithOutHostHostDoesNotMatch()
    {
        $this->expectException(ResourceNotFoundException::class);
        $coll = new RouteCollection();
        $coll->add('foo', new Route('/foo/{foo}', [], [], [], '{locale}.example.com'));

        $matcher = $this->getUrlMatcher($coll, new RequestContext('', 'GET', 'example.com'));
        $matcher->match('/foo/bar');
    }

    public function testPathIsCaseSensitive()
    {
        $this->expectException(ResourceNotFoundException::class);
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
        $this->assertEquals(['_route' => 'foo', 'locale' => 'en'], $matcher->match('/'));
    }

    public function testNoConfiguration()
    {
        $this->expectException(NoConfigurationException::class);
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
        $this->assertEquals(['_route' => 'a'], $matcher->match('/p/a'));
        $this->assertEquals(['_route' => 'baz', 'baz' => 'p'], $matcher->match('/p'));
        $this->assertEquals(['_route' => 'buz'], $matcher->match('/prefix/buz'));
    }

    public function testSchemeAndMethodMismatch()
    {
        $this->expectException(ResourceNotFoundException::class);
        $this->expectExceptionMessage('No routes found for "/".');
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
        $this->assertEquals(['_route' => 'c', 'a' => 'a'], $matcher->match('/aa'));
        $this->assertEquals(['_route' => 'f', 'b' => 'b', 'a' => 'a'], $matcher->match('/ba'));
    }

    public function testUnicodeRoute()
    {
        $coll = new RouteCollection();
        $coll->add('a', new Route('/{a}', [], ['a' => '.'], ['utf8' => false]));
        $coll->add('b', new Route('/{a}', [], ['a' => '.'], ['utf8' => true]));

        $matcher = $this->getUrlMatcher($coll);
        $this->assertEquals(['_route' => 'b', 'a' => 'é'], $matcher->match('/é'));
    }

    public function testRequirementWithCapturingGroup()
    {
        $coll = new RouteCollection();
        $coll->add('a', new Route('/{a}/{b}', [], ['a' => '(a|b)']));

        $matcher = $this->getUrlMatcher($coll);
        $this->assertEquals(['_route' => 'a', 'a' => 'a', 'b' => 'b'], $matcher->match('/a/b'));
    }

    public function testDotAllWithCatchAll()
    {
        $coll = new RouteCollection();
        $coll->add('a', new Route('/{id}.html', [], ['id' => '.+']));
        $coll->add('b', new Route('/{all}', [], ['all' => '.+']));

        $matcher = $this->getUrlMatcher($coll);
        $this->assertEquals(['_route' => 'a', 'id' => 'foo/bar'], $matcher->match('/foo/bar.html'));
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
        $this->assertEquals($expected, $matcher->match('/an_app/an_action/unused'));
    }

    public function testUtf8Prefix()
    {
        $coll = new RouteCollection();
        $coll->add('a', new Route('/é{foo}', [], [], ['utf8' => true]));
        $coll->add('b', new Route('/è{bar}', [], [], ['utf8' => true]));

        $matcher = $this->getUrlMatcher($coll);
        $this->assertEquals('a', $matcher->match('/éo')['_route']);
    }

    public function testUtf8AndMethodMatching()
    {
        $coll = new RouteCollection();
        $coll->add('a', new Route('/admin/api/list/{shortClassName}/{id}.{_format}', [], [], ['utf8' => true], '', [], ['PUT']));
        $coll->add('b', new Route('/admin/api/package.{_format}', [], [], [], '', [], ['POST']));
        $coll->add('c', new Route('/admin/api/package.{_format}', ['_format' => 'json'], [], [], '', [], ['GET']));

        $matcher = $this->getUrlMatcher($coll);
        $this->assertEquals('c', $matcher->match('/admin/api/package.json')['_route']);
    }

    public function testHostWithDot()
    {
        $coll = new RouteCollection();
        $coll->add('a', new Route('/foo', [], [], [], 'foo.example.com'));
        $coll->add('b', new Route('/bar/{baz}'));

        $matcher = $this->getUrlMatcher($coll);
        $this->assertEquals('b', $matcher->match('/bar/abc.123')['_route']);
    }

    public function testSlashVariant()
    {
        $coll = new RouteCollection();
        $coll->add('a', new Route('/foo/{bar}', [], ['bar' => '.*']));

        $matcher = $this->getUrlMatcher($coll);
        $this->assertEquals('a', $matcher->match('/foo/')['_route']);
    }

    public function testSlashVariant2()
    {
        $coll = new RouteCollection();
        $coll->add('a', new Route('/foo/{bär}/', [], ['bär' => '.*'], ['utf8' => true]));

        $matcher = $this->getUrlMatcher($coll);
        $this->assertEquals(['_route' => 'a', 'bär' => 'bar'], $matcher->match('/foo/bar/'));
    }

    public function testSlashWithVerb()
    {
        $coll = new RouteCollection();
        $coll->add('a', new Route('/{foo}', [], [], [], '', [], ['put', 'delete']));
        $coll->add('b', new Route('/bar/'));

        $matcher = $this->getUrlMatcher($coll);
        $this->assertSame(['_route' => 'b'], $matcher->match('/bar/'));

        $coll = new RouteCollection();
        $coll->add('a', new Route('/dav/{foo}', [], ['foo' => '.*'], [], '', [], ['GET', 'OPTIONS']));

        $matcher = $this->getUrlMatcher($coll, new RequestContext('', 'OPTIONS'));
        $expected = [
            '_route' => 'a',
            'foo' => 'files/bar/',
        ];
        $this->assertEquals($expected, $matcher->match('/dav/files/bar/'));
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
        $this->assertEquals($expected, $matcher->match('/api/customers/123/contactpersons'));

        $coll = new RouteCollection();
        $coll->add('a', new Route('/api/customers/{customerId}/contactpersons/', [], [], [], '', [], ['get']));
        $coll->add('b', new Route('/api/customers/{customerId}/contactpersons', [], [], [], '', [], ['post']));

        $matcher = $this->getUrlMatcher($coll, new RequestContext('', 'POST'));
        $expected = [
            '_route' => 'b',
            'customerId' => '123',
        ];
        $this->assertEquals($expected, $matcher->match('/api/customers/123/contactpersons'));
    }

    public function testGreedyTrailingRequirement()
    {
        $coll = new RouteCollection();
        $coll->add('a', new Route('/{a}', [], ['a' => '.+']));

        $matcher = $this->getUrlMatcher($coll);

        $this->assertEquals(['_route' => 'a', 'a' => 'foo'], $matcher->match('/foo'));
        $this->assertEquals(['_route' => 'a', 'a' => 'foo/'], $matcher->match('/foo/'));
    }

    public function testTrailingRequirementWithDefault()
    {
        $coll = new RouteCollection();
        $coll->add('a', new Route('/fr-fr/{a}', ['a' => 'aaa'], ['a' => '.+']));
        $coll->add('b', new Route('/en-en/{b}', ['b' => 'bbb'], ['b' => '.*']));

        $matcher = $this->getUrlMatcher($coll);

        $this->assertEquals(['_route' => 'a', 'a' => 'aaa'], $matcher->match('/fr-fr'));
        $this->assertEquals(['_route' => 'a', 'a' => 'AAA'], $matcher->match('/fr-fr/AAA'));
        $this->assertEquals(['_route' => 'b', 'b' => 'bbb'], $matcher->match('/en-en'));
        $this->assertEquals(['_route' => 'b', 'b' => 'BBB'], $matcher->match('/en-en/BBB'));
    }

    public function testTrailingRequirementWithDefaultA()
    {
        $coll = new RouteCollection();
        $coll->add('a', new Route('/fr-fr/{a}', ['a' => 'aaa'], ['a' => '.+']));

        $matcher = $this->getUrlMatcher($coll);

        $this->expectException(ResourceNotFoundException::class);
        $matcher->match('/fr-fr/');
    }

    public function testTrailingRequirementWithDefaultB()
    {
        $coll = new RouteCollection();
        $coll->add('b', new Route('/en-en/{b}', ['b' => 'bbb'], ['b' => '.*']));

        $matcher = $this->getUrlMatcher($coll);

        $this->assertEquals(['_route' => 'b', 'b' => ''], $matcher->match('/en-en/'));
    }

    public function testRestrictiveTrailingRequirementWithStaticRouteAfter()
    {
        $coll = new RouteCollection();
        $coll->add('a', new Route('/hello{_}', [], ['_' => '/(?!/)']));
        $coll->add('b', new Route('/hello'));

        $matcher = $this->getUrlMatcher($coll);

        $this->assertEquals(['_route' => 'a', '_' => '/'], $matcher->match('/hello/'));
    }

    public function testUtf8VarName()
    {
        $collection = new RouteCollection();
        $collection->add('foo', new Route('/foo/{bär}/{bäz?foo}', [], [], ['utf8' => true]));

        $matcher = $this->getUrlMatcher($collection);

        $this->assertEquals(['_route' => 'foo', 'bär' => 'baz', 'bäz' => 'foo'], $matcher->match('/foo/baz'));
    }

    protected function getUrlMatcher(RouteCollection $routes, RequestContext $context = null)
    {
        return new UrlMatcher($routes, $context ?? new RequestContext());
    }
}
