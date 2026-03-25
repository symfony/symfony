<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\WebProfilerBundle\Tests\Profiler;

use Symfony\Bundle\WebProfilerBundle\Profiler\ProfilerDataSerializer;
use Symfony\Bundle\WebProfilerBundle\Tests\TestCase;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpKernel\DataCollector\ExceptionDataCollector;
use Symfony\Component\HttpKernel\DataCollector\LoggerDataCollector;
use Symfony\Component\HttpKernel\DataCollector\MemoryDataCollector;
use Symfony\Component\HttpKernel\DataCollector\RequestDataCollector;
use Symfony\Component\HttpKernel\DataCollector\TimeDataCollector;
use Symfony\Component\HttpKernel\Profiler\Profile;
use Symfony\Component\VarDumper\Cloner\Data;

class ProfilerDataSerializerTest extends TestCase
{
    public function testGetSupportedCollectors()
    {
        $this->assertSame(['request', 'exception', 'memory'], ProfilerDataSerializer::getSupportedCollectors());
    }

    public function testBuildOverview()
    {
        $requestCollector = $this->createStub(RequestDataCollector::class);
        $requestCollector->method('getName')->willReturn('request');
        $exceptionCollector = $this->createStub(ExceptionDataCollector::class);
        $exceptionCollector->method('getName')->willReturn('exception');
        $exceptionCollector->method('hasException')->willReturn(false);

        $profile = new Profile('abc123');
        $profile->setMethod('GET');
        $profile->setUrl('http://localhost/');
        $profile->setStatusCode(200);
        $profile->setIp('127.0.0.1');
        $profile->setTime(1700000000);
        $profile->addCollector($requestCollector);
        $profile->addCollector($exceptionCollector);

        $data = ProfilerDataSerializer::buildOverview($profile);

        $this->assertSame('abc123', $data['token']);
        $this->assertSame('GET', $data['method']);
        $this->assertSame('http://localhost/', $data['url']);
        $this->assertSame(200, $data['status_code']);
        $this->assertSame('127.0.0.1', $data['ip']);
        $this->assertSame(1700000000, $data['time']);
        if (method_exists(Profile::class, 'hasErrors')) {
            $this->assertFalse($data['has_errors']);
        } else {
            $this->assertArrayNotHasKey('has_errors', $data);
        }
        $this->assertSame(['request', 'exception'], $data['collectors']);
        $this->assertSame('OK', $data['status_text']);
    }

    public function testBuildOverviewWithNullStatusCode()
    {
        $profile = new Profile('abc123');
        $profile->setMethod('GET');
        $profile->setUrl('http://localhost/');
        $profile->setIp('127.0.0.1');
        $profile->setTime(1700000000);

        $data = ProfilerDataSerializer::buildOverview($profile);

        $this->assertArrayNotHasKey('status_text', $data);
    }

    public function testBuildOverviewMetrics()
    {
        $timeCollector = $this->createStub(TimeDataCollector::class);
        $timeCollector->method('getName')->willReturn('time');
        $timeCollector->method('getDuration')->willReturn(142.3);

        $memoryCollector = $this->createStub(MemoryDataCollector::class);
        $memoryCollector->method('getName')->willReturn('memory');
        $memoryCollector->method('getMemory')->willReturn(16777216);

        $exceptionCollector = $this->createStub(ExceptionDataCollector::class);
        $exceptionCollector->method('getName')->willReturn('exception');
        $exceptionCollector->method('hasException')->willReturn(true);

        $loggerCollector = $this->createStub(LoggerDataCollector::class);
        $loggerCollector->method('getName')->willReturn('logger');
        $loggerCollector->method('countErrors')->willReturn(2);

        $profile = new Profile('tok');
        $profile->setMethod('GET');
        $profile->setUrl('http://localhost/');
        $profile->setStatusCode(200);
        $profile->setIp('127.0.0.1');
        $profile->setTime(0);
        $profile->addCollector($timeCollector);
        $profile->addCollector($memoryCollector);
        $profile->addCollector($exceptionCollector);
        $profile->addCollector($loggerCollector);

        $data = ProfilerDataSerializer::buildOverview($profile);

        $this->assertSame(
            ['duration_ms' => 142.3, 'memory_bytes' => 16777216, 'has_exception' => true, 'error_count' => 2],
            $data['metrics']
        );
    }

    public function testBuildOverviewWithNoMetricsCollectors()
    {
        $profile = new Profile('tok');
        $profile->setMethod('GET');
        $profile->setUrl('http://localhost/');
        $profile->setStatusCode(200);
        $profile->setIp('127.0.0.1');
        $profile->setTime(0);

        $data = ProfilerDataSerializer::buildOverview($profile);

        $this->assertArrayNotHasKey('metrics', $data);
    }

    public function testBuildCollectorDataMemory()
    {
        $memoryCollector = $this->createStub(MemoryDataCollector::class);
        $memoryCollector->method('getMemory')->willReturn(1024);
        $memoryCollector->method('getMemoryLimit')->willReturn(-1);

        $memoryCollector->method('getName')->willReturn('memory');
        $profile = new Profile('test');
        $profile->addCollector($memoryCollector);

        $data = ProfilerDataSerializer::buildCollectorData($profile, 'memory');

        $this->assertSame(['memory' => 1024, 'memory_limit' => -1], $data);
    }

    public function testBuildCollectorDataExceptionWithException()
    {
        $trace = [
            ['class' => 'App\\Controller', 'function' => 'index', 'args' => ['arg1']],
            ['class' => 'Symfony\\Kernel', 'function' => 'handle', 'args' => []],
            ['class' => 'App\\Bootstrap', 'function' => 'run', 'args' => ['arg1', 'arg2']],
        ];

        $exceptionCollector = $this->createStub(ExceptionDataCollector::class);
        $exceptionCollector->method('hasException')->willReturn(true);
        $exceptionCollector->method('getMessage')->willReturn('Something went wrong');
        $exceptionCollector->method('getCode')->willReturn(0);
        $exceptionCollector->method('getStatusCode')->willReturn(500);
        $exceptionCollector->method('getTrace')->willReturn($trace);

        $exceptionCollector->method('getName')->willReturn('exception');
        $profile = new Profile('test');
        $profile->addCollector($exceptionCollector);

        $data = ProfilerDataSerializer::buildCollectorData($profile, 'exception');

        $this->assertTrue($data['has_exception']);
        $this->assertSame('Something went wrong', $data['message']);
        $this->assertSame(0, $data['code']);
        $this->assertSame(500, $data['status_code']);
        $this->assertCount(3, $data['trace']);

        foreach ($data['trace'] as $frame) {
            $this->assertArrayNotHasKey('args', $frame);
            $this->assertArrayHasKey('class', $frame);
            $this->assertArrayHasKey('function', $frame);
        }
    }

    public function testBuildCollectorDataExceptionWithoutException()
    {
        $exceptionCollector = $this->createStub(ExceptionDataCollector::class);
        $exceptionCollector->method('hasException')->willReturn(false);

        $exceptionCollector->method('getName')->willReturn('exception');
        $profile = new Profile('test');
        $profile->addCollector($exceptionCollector);

        $data = ProfilerDataSerializer::buildCollectorData($profile, 'exception');

        $this->assertSame(['has_exception' => false], $data);
    }

    public function testBuildCollectorDataExceptionTraceLimitedTo50Frames()
    {
        $trace = [];
        for ($i = 0; $i < 60; ++$i) {
            $trace[] = ['class' => 'Foo', 'function' => 'bar'.$i, 'args' => []];
        }

        $exceptionCollector = $this->createStub(ExceptionDataCollector::class);
        $exceptionCollector->method('hasException')->willReturn(true);
        $exceptionCollector->method('getMessage')->willReturn('Error');
        $exceptionCollector->method('getCode')->willReturn(0);
        $exceptionCollector->method('getStatusCode')->willReturn(500);
        $exceptionCollector->method('getTrace')->willReturn($trace);

        $exceptionCollector->method('getName')->willReturn('exception');
        $profile = new Profile('test');
        $profile->addCollector($exceptionCollector);

        $data = ProfilerDataSerializer::buildCollectorData($profile, 'exception');

        $this->assertCount(50, $data['trace']);
    }

    public function testBuildCollectorDataRequestRedaction()
    {
        $requestCollector = $this->createStub(RequestDataCollector::class);
        $requestCollector->method('getMethod')->willReturn('POST');
        $requestCollector->method('getPathInfo')->willReturn('/api/login');
        $requestCollector->method('getRoute')->willReturn('app_login');
        $requestCollector->method('getRouteParams')->willReturn(['_route' => 'app_login']);
        $requestCollector->method('getStatusCode')->willReturn(200);
        $requestCollector->method('getStatusText')->willReturn('OK');
        $requestCollector->method('getContentType')->willReturn('application/json');
        $requestCollector->method('getFormat')->willReturn('json');
        $requestCollector->method('getLocale')->willReturn('en');
        $requestCollector->method('getController')->willReturn('App\\Controller::login');
        $requestCollector->method('getRequestQuery')->willReturn(new ParameterBag(['page' => '1', 'sort' => 'asc']));
        $requestCollector->method('getRequestRequest')->willReturn(new ParameterBag([]));
        $requestCollector->method('getRequestHeaders')->willReturn(new ParameterBag(['authorization' => 'Bearer xxx', 'content-type' => 'application/json']));
        $requestCollector->method('getRequestCookies')->willReturn(new ParameterBag(['session' => 'abc123', 'remember_me' => 'xyz']));
        $requestCollector->method('getRequestServer')->willReturn(new ParameterBag(['APP_SECRET' => 'my-secret', 'SERVER_NAME' => 'localhost']));
        $requestCollector->method('getResponseHeaders')->willReturn(new ParameterBag(['content-type' => 'application/json']));
        $requestCollector->method('getResponseCookies')->willReturn(new ParameterBag([]));
        $requestCollector->method('getSessionAttributes')->willReturn([]);
        $requestCollector->method('getDotenvVars')->willReturn(new ParameterBag([]));

        $requestCollector->method('getName')->willReturn('request');
        $profile = new Profile('test');
        $profile->addCollector($requestCollector);

        $data = ProfilerDataSerializer::buildCollectorData($profile, 'request');

        // Sensitive headers are redacted
        $this->assertSame('***REDACTED***', $data['request_headers']['authorization']);
        // Non-sensitive headers are preserved
        $this->assertSame('application/json', $data['request_headers']['content-type']);

        // All cookies are redacted
        $this->assertSame('***REDACTED***', $data['request_cookies']['session']);
        $this->assertSame('***REDACTED***', $data['request_cookies']['remember_me']);

        // Sensitive server vars are redacted
        $this->assertSame('***REDACTED***', $data['request_server']['APP_SECRET']);
        // Non-sensitive server vars are preserved
        $this->assertSame('localhost', $data['request_server']['SERVER_NAME']);

        // Non-sensitive query params are preserved
        $this->assertSame('1', $data['request_query']['page']);
        $this->assertSame('asc', $data['request_query']['sort']);
    }

    public function testBuildCollectorDataRequestResolvesDataObjects()
    {
        // RequestDataCollector wraps header/param values in VarDumper Data objects.
        // Verify they are resolved to plain values before JSON serialization.
        $headerData = $this->createStub(Data::class);
        $headerData->method('getValue')->willReturn(['application/json']);

        $requestCollector = $this->createStub(RequestDataCollector::class);
        $requestCollector->method('getMethod')->willReturn('GET');
        $requestCollector->method('getPathInfo')->willReturn('/');
        $requestCollector->method('getRoute')->willReturn('app_home');
        $requestCollector->method('getRouteParams')->willReturn(['_route' => 'app_home']);
        $requestCollector->method('getStatusCode')->willReturn(200);
        $requestCollector->method('getStatusText')->willReturn('OK');
        $requestCollector->method('getContentType')->willReturn('text/html');
        $requestCollector->method('getFormat')->willReturn('html');
        $requestCollector->method('getLocale')->willReturn('en');
        $requestCollector->method('getController')->willReturn('App\\Controller::index');
        $requestCollector->method('getRequestQuery')->willReturn(new ParameterBag([]));
        $requestCollector->method('getRequestRequest')->willReturn(new ParameterBag([]));
        // Simulate the real collector: header values are Data objects, not plain strings.
        $requestCollector->method('getRequestHeaders')->willReturn(new ParameterBag(['content-type' => $headerData]));
        $requestCollector->method('getRequestCookies')->willReturn(new ParameterBag([]));
        $requestCollector->method('getRequestServer')->willReturn(new ParameterBag([]));
        $requestCollector->method('getResponseHeaders')->willReturn(new ParameterBag([]));
        $requestCollector->method('getResponseCookies')->willReturn(new ParameterBag([]));
        $requestCollector->method('getSessionAttributes')->willReturn([]);
        $requestCollector->method('getDotenvVars')->willReturn(new ParameterBag([]));

        $requestCollector->method('getName')->willReturn('request');
        $profile = new Profile('test');
        $profile->addCollector($requestCollector);

        $data = ProfilerDataSerializer::buildCollectorData($profile, 'request');

        // The Data object must be resolved; json_encode on an unresolved Data gives '{}'.
        $this->assertNotInstanceOf(Data::class, $data['request_headers']['content-type']);
        $this->assertSame(['application/json'], $data['request_headers']['content-type']);
        $this->assertSame('["application\/json"]', json_encode($data['request_headers']['content-type']));
    }

    public function testBuildCollectorDataRequestResolvesNestedDataObjects()
    {
        // Test that Data objects nested inside plain arrays are also resolved.
        $nestedData = $this->createStub(Data::class);
        $nestedData->method('getValue')->willReturn('resolved-value');

        $requestCollector = $this->createStub(RequestDataCollector::class);
        $requestCollector->method('getMethod')->willReturn('GET');
        $requestCollector->method('getPathInfo')->willReturn('/');
        $requestCollector->method('getRoute')->willReturn('app_home');
        $requestCollector->method('getRouteParams')->willReturn([]);
        $requestCollector->method('getStatusCode')->willReturn(200);
        $requestCollector->method('getStatusText')->willReturn('OK');
        $requestCollector->method('getContentType')->willReturn('text/html');
        $requestCollector->method('getFormat')->willReturn('html');
        $requestCollector->method('getLocale')->willReturn('en');
        $requestCollector->method('getController')->willReturn('App\\Controller::index');
        // Nested: plain array wrapping a Data object
        $requestCollector->method('getRequestQuery')->willReturn(new ParameterBag(['nested' => ['deep' => $nestedData]]));
        $requestCollector->method('getRequestRequest')->willReturn(new ParameterBag([]));
        $requestCollector->method('getRequestHeaders')->willReturn(new ParameterBag([]));
        $requestCollector->method('getRequestCookies')->willReturn(new ParameterBag([]));
        $requestCollector->method('getRequestServer')->willReturn(new ParameterBag([]));
        $requestCollector->method('getResponseHeaders')->willReturn(new ParameterBag([]));
        $requestCollector->method('getResponseCookies')->willReturn(new ParameterBag([]));
        $requestCollector->method('getSessionAttributes')->willReturn([]);
        $requestCollector->method('getDotenvVars')->willReturn(new ParameterBag([]));

        $requestCollector->method('getName')->willReturn('request');
        $profile = new Profile('test');
        $profile->addCollector($requestCollector);

        $data = ProfilerDataSerializer::buildCollectorData($profile, 'request');

        $this->assertSame('resolved-value', $data['request_query']['nested']['deep']);
    }

    public function testBuildCollectorDataUnsupportedCollector()
    {
        $profile = new Profile('test');

        $this->expectException(\InvalidArgumentException::class);

        ProfilerDataSerializer::buildCollectorData($profile, 'unsupported');
    }

    public function testBuildCollectorDataRequestRedactsQueryAndPostParams()
    {
        $requestCollector = $this->createStub(RequestDataCollector::class);
        $requestCollector->method('getMethod')->willReturn('POST');
        $requestCollector->method('getPathInfo')->willReturn('/api/login');
        $requestCollector->method('getRoute')->willReturn('app_login');
        $requestCollector->method('getRouteParams')->willReturn(['_route' => 'app_login']);
        $requestCollector->method('getStatusCode')->willReturn(200);
        $requestCollector->method('getStatusText')->willReturn('OK');
        $requestCollector->method('getContentType')->willReturn('application/json');
        $requestCollector->method('getFormat')->willReturn('json');
        $requestCollector->method('getLocale')->willReturn('en');
        $requestCollector->method('getController')->willReturn('App\\Controller::login');
        $requestCollector->method('getRequestQuery')->willReturn(new ParameterBag(['api_token' => 'secret123', 'page' => '1']));
        $requestCollector->method('getRequestRequest')->willReturn(new ParameterBag(['_password' => 'mypass', 'username' => 'john', 'csrf_token' => 'abc']));
        $requestCollector->method('getRequestHeaders')->willReturn(new ParameterBag(['content-type' => 'application/json']));
        $requestCollector->method('getRequestCookies')->willReturn(new ParameterBag([]));
        $requestCollector->method('getRequestServer')->willReturn(new ParameterBag([]));
        $requestCollector->method('getResponseHeaders')->willReturn(new ParameterBag([]));
        $requestCollector->method('getResponseCookies')->willReturn(new ParameterBag([]));
        $requestCollector->method('getSessionAttributes')->willReturn([]);
        $requestCollector->method('getDotenvVars')->willReturn(new ParameterBag([]));

        $requestCollector->method('getName')->willReturn('request');
        $profile = new Profile('test');
        $profile->addCollector($requestCollector);

        $data = ProfilerDataSerializer::buildCollectorData($profile, 'request');

        // Sensitive query params are redacted
        $this->assertSame('***REDACTED***', $data['request_query']['api_token']);
        // Non-sensitive query params are preserved
        $this->assertSame('1', $data['request_query']['page']);

        // Sensitive POST params are redacted
        $this->assertSame('***REDACTED***', $data['request_request']['_password']);
        $this->assertSame('***REDACTED***', $data['request_request']['csrf_token']);
        // Non-sensitive POST params are preserved
        $this->assertSame('john', $data['request_request']['username']);
    }

    public function testBuildCollectorDataRequestWithDataController()
    {
        $controllerData = $this->createStub(Data::class);
        $controllerData->method('getValue')->willReturn('App\\Controller\\FooController::barAction');

        $requestCollector = $this->createStub(RequestDataCollector::class);
        $requestCollector->method('getMethod')->willReturn('GET');
        $requestCollector->method('getPathInfo')->willReturn('/foo/bar');
        $requestCollector->method('getRoute')->willReturn('app_foo_bar');
        $requestCollector->method('getRouteParams')->willReturn([]);
        $requestCollector->method('getStatusCode')->willReturn(200);
        $requestCollector->method('getStatusText')->willReturn('OK');
        $requestCollector->method('getContentType')->willReturn('text/html');
        $requestCollector->method('getFormat')->willReturn('html');
        $requestCollector->method('getLocale')->willReturn('en');
        $requestCollector->method('getController')->willReturn($controllerData);
        $requestCollector->method('getRequestQuery')->willReturn(new ParameterBag([]));
        $requestCollector->method('getRequestRequest')->willReturn(new ParameterBag([]));
        $requestCollector->method('getRequestHeaders')->willReturn(new ParameterBag([]));
        $requestCollector->method('getRequestCookies')->willReturn(new ParameterBag([]));
        $requestCollector->method('getRequestServer')->willReturn(new ParameterBag([]));
        $requestCollector->method('getResponseHeaders')->willReturn(new ParameterBag([]));
        $requestCollector->method('getResponseCookies')->willReturn(new ParameterBag([]));
        $requestCollector->method('getSessionAttributes')->willReturn([]);
        $requestCollector->method('getDotenvVars')->willReturn(new ParameterBag([]));

        $requestCollector->method('getName')->willReturn('request');
        $profile = new Profile('test');
        $profile->addCollector($requestCollector);

        $data = ProfilerDataSerializer::buildCollectorData($profile, 'request');

        $this->assertSame('App\\Controller\\FooController::barAction', $data['controller']);
    }

    public function testBuildCollectorDataRequestWithNullDataController()
    {
        // Data::getValue() can return null; the null coalesce fallback produces ''.
        $controllerData = $this->createStub(Data::class);
        $controllerData->method('getValue')->willReturn(null);

        $requestCollector = $this->createStub(RequestDataCollector::class);
        $requestCollector->method('getMethod')->willReturn('GET');
        $requestCollector->method('getPathInfo')->willReturn('/some/action');
        $requestCollector->method('getRoute')->willReturn('app_some_action');
        $requestCollector->method('getRouteParams')->willReturn([]);
        $requestCollector->method('getStatusCode')->willReturn(200);
        $requestCollector->method('getStatusText')->willReturn('OK');
        $requestCollector->method('getContentType')->willReturn('text/html');
        $requestCollector->method('getFormat')->willReturn('html');
        $requestCollector->method('getLocale')->willReturn('en');
        $requestCollector->method('getController')->willReturn($controllerData);
        $requestCollector->method('getRequestQuery')->willReturn(new ParameterBag([]));
        $requestCollector->method('getRequestRequest')->willReturn(new ParameterBag([]));
        $requestCollector->method('getRequestHeaders')->willReturn(new ParameterBag([]));
        $requestCollector->method('getRequestCookies')->willReturn(new ParameterBag([]));
        $requestCollector->method('getRequestServer')->willReturn(new ParameterBag([]));
        $requestCollector->method('getResponseHeaders')->willReturn(new ParameterBag([]));
        $requestCollector->method('getResponseCookies')->willReturn(new ParameterBag([]));
        $requestCollector->method('getSessionAttributes')->willReturn([]);
        $requestCollector->method('getDotenvVars')->willReturn(new ParameterBag([]));

        $requestCollector->method('getName')->willReturn('request');
        $profile = new Profile('test');
        $profile->addCollector($requestCollector);

        $data = ProfilerDataSerializer::buildCollectorData($profile, 'request');

        $this->assertIsString($data['controller']);
        $this->assertSame('', $data['controller']);
    }

    public function testBuildCollectorDataRequestRedactsResponseAndSession()
    {
        $requestCollector = $this->createStub(RequestDataCollector::class);
        $requestCollector->method('getMethod')->willReturn('GET');
        $requestCollector->method('getPathInfo')->willReturn('/dashboard');
        $requestCollector->method('getRoute')->willReturn('app_dashboard');
        $requestCollector->method('getRouteParams')->willReturn([]);
        $requestCollector->method('getStatusCode')->willReturn(200);
        $requestCollector->method('getStatusText')->willReturn('OK');
        $requestCollector->method('getContentType')->willReturn('text/html');
        $requestCollector->method('getFormat')->willReturn('html');
        $requestCollector->method('getLocale')->willReturn('en');
        $requestCollector->method('getController')->willReturn('App\\Controller::dashboard');
        $requestCollector->method('getRequestQuery')->willReturn(new ParameterBag([]));
        $requestCollector->method('getRequestRequest')->willReturn(new ParameterBag([]));
        $requestCollector->method('getRequestHeaders')->willReturn(new ParameterBag([]));
        $requestCollector->method('getRequestCookies')->willReturn(new ParameterBag([]));
        $requestCollector->method('getRequestServer')->willReturn(new ParameterBag([]));
        $requestCollector->method('getResponseHeaders')->willReturn(new ParameterBag([]));
        $requestCollector->method('getResponseCookies')->willReturn(new ParameterBag(['PHPSESSID' => 'abc123']));
        $requestCollector->method('getSessionAttributes')->willReturn(['user_id' => 42, 'role' => 'admin']);
        $requestCollector->method('getDotenvVars')->willReturn(new ParameterBag(['APP_SECRET' => 'supersecret', 'APP_ENV' => 'dev']));

        $requestCollector->method('getName')->willReturn('request');
        $profile = new Profile('test');
        $profile->addCollector($requestCollector);

        $data = ProfilerDataSerializer::buildCollectorData($profile, 'request');

        // All response cookies are redacted
        $this->assertSame('***REDACTED***', $data['response_cookies']['PHPSESSID']);

        // All session attributes are redacted
        $this->assertSame('***REDACTED***', $data['session_attributes']['user_id']);
        $this->assertSame('***REDACTED***', $data['session_attributes']['role']);

        // Sensitive dotenv vars are redacted
        $this->assertSame('***REDACTED***', $data['dotenv_vars']['APP_SECRET']);
        // Non-sensitive dotenv vars are preserved
        $this->assertSame('dev', $data['dotenv_vars']['APP_ENV']);
    }
}
