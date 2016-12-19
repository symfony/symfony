<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\WebProfilerBundle\Tests\Controller;

use Symfony\Bundle\WebProfilerBundle\Controller\ProfilerController;
use Symfony\Component\HttpKernel\Profiler\Profile;
use Symfony\Component\HttpFoundation\Request;

class ProfilerControllerTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @dataProvider getEmptyTokenCases
     */
    public function testEmptyToken($token)
    {
        $urlGenerator = $this->getMockBuilder('Symfony\Component\Routing\Generator\UrlGeneratorInterface')->getMock();
        $twig = $this->getMockBuilder('Twig_Environment')->disableOriginalConstructor()->getMock();
        $profiler = $this
            ->getMockBuilder('Symfony\Component\HttpKernel\Profiler\Profiler')
            ->disableOriginalConstructor()
            ->getMock();

        $controller = new ProfilerController($urlGenerator, $profiler, $twig, array());

        $response = $controller->toolbarAction(Request::create('/_wdt/empty'), $token);
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function getEmptyTokenCases()
    {
        return array(
            array(null),
            // "empty" is also a valid empty token case, see https://github.com/symfony/symfony/issues/10806
            array('empty'),
        );
    }

    public function testReturns404onTokenNotFound()
    {
        $urlGenerator = $this->getMockBuilder('Symfony\Component\Routing\Generator\UrlGeneratorInterface')->getMock();
        $twig = $this->getMockBuilder('Twig_Environment')->disableOriginalConstructor()->getMock();
        $profiler = $this
            ->getMockBuilder('Symfony\Component\HttpKernel\Profiler\Profiler')
            ->disableOriginalConstructor()
            ->getMock();

        $controller = new ProfilerController($urlGenerator, $profiler, $twig, array());

        $profiler
            ->expects($this->exactly(2))
            ->method('loadProfile')
            ->will($this->returnCallback(function ($token) {
                if ('found' == $token) {
                    return new Profile($token);
                }
            }))
        ;

        $response = $controller->toolbarAction(Request::create('/_wdt/found'), 'found');
        $this->assertEquals(200, $response->getStatusCode());

        $response = $controller->toolbarAction(Request::create('/_wdt/notFound'), 'notFound');
        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testSearchResult()
    {
        $urlGenerator = $this->getMockBuilder('Symfony\Component\Routing\Generator\UrlGeneratorInterface')->getMock();
        $twig = $this->getMockBuilder('Twig_Environment')->disableOriginalConstructor()->getMock();
        $profiler = $this
            ->getMockBuilder('Symfony\Component\HttpKernel\Profiler\Profiler')
            ->disableOriginalConstructor()
            ->getMock();

        $controller = new ProfilerController($urlGenerator, $profiler, $twig, array());

        $tokens = array(
            array(
                'token' => 'token1',
                'ip' => '127.0.0.1',
                'method' => 'GET',
                'url' => 'http://example.com/',
                'time' => 0,
                'parent' => null,
                'status_code' => 200,
            ),
            array(
                'token' => 'token2',
                'ip' => '127.0.0.1',
                'method' => 'GET',
                'url' => 'http://example.com/not_found',
                'time' => 0,
                'parent' => null,
                'status_code' => 404,
            ),
        );
        $profiler
            ->expects($this->once())
            ->method('find')
            ->will($this->returnValue($tokens));

        $twig->expects($this->once())
            ->method('render')
            ->with($this->stringEndsWith('results.html.twig'), $this->equalTo(array(
                'token' => 'empty',
                'profile' => null,
                'tokens' => $tokens,
                'ip' => '127.0.0.1',
                'method' => 'GET',
                'url' => 'http://example.com/',
                'start' => null,
                'end' => null,
                'limit' => 2,
                'panel' => null,
            )));

        $response = $controller->searchResultsAction(
            Request::create(
                '/_profiler/empty/search/results',
                'GET',
                array('limit' => 2, 'ip' => '127.0.0.1', 'method' => 'GET', 'url' => 'http://example.com/')
            ),
            'empty'
        );
        $this->assertEquals(200, $response->getStatusCode());
    }
}
