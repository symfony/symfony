<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Routing\Tests\Command;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Exception\InvalidArgumentException;
use Symfony\Component\Console\Tester\CommandCompletionTester;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Routing\Command\RouteLintCommand;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Routing\RouterInterface;

class RouteLintCommandTest extends TestCase
{
    public function testNoIssues()
    {
        $tester = $this->createCommandTester([
            'home' => new Route('/'),
            'blog' => new Route('/blog'),
        ]);

        $ret = $tester->execute(['--format' => 'txt'], ['decorated' => false]);

        $this->assertSame(0, $ret);
        $this->assertStringContainsString('No shadowed, duplicate or ambiguous routes found.', $this->getNormalizedDisplay($tester));
    }

    public function testErrorsReturnFailure()
    {
        $tester = $this->createCommandTester([
            'first' => new Route('/foo'),
            'second' => new Route('/foo'),
            'blog_show' => new Route('/blog/{slug}'),
            'blog_latest' => new Route('/blog/latest'),
        ]);

        $ret = $tester->execute(['--format' => 'txt'], ['decorated' => false]);
        $display = $this->getNormalizedDisplay($tester);

        $this->assertSame(1, $ret);
        $this->assertStringContainsString('ERROR Route "second" (/foo) has the same path, host, methods, schemes and condition as route "first" (/foo)', $display);
        $this->assertStringContainsString('ERROR Route "blog_latest" (/blog/latest) can never match because route "blog_show" (/blog/{slug})', $display);
        $this->assertStringContainsString('Found 2 error(s) and 0 warning(s) in the routes.', $display);
    }

    public function testWarningsOnlyReturnSuccess()
    {
        $tester = $this->createCommandTester([
            'blog_show' => new Route('/blog/{slug}', methods: ['GET']),
            'blog_latest' => new Route('/blog/latest'),
        ]);

        $ret = $tester->execute(['--format' => 'txt'], ['decorated' => false]);
        $display = $this->getNormalizedDisplay($tester);

        $this->assertSame(0, $ret);
        $this->assertStringContainsString('WARNING Route "blog_latest" (/blog/latest) overlaps route "blog_show" (/blog/{slug})', $display);
        $this->assertStringContainsString('Found 1 warning(s) in the routes.', $display);
        $this->assertStringNotContainsString('error(s)', $display);
    }

    public function testErrorsAndWarnings()
    {
        $tester = $this->createCommandTester([
            'blog_show' => new Route('/blog/{slug}', methods: ['GET']),
            'blog_latest' => new Route('/blog/latest'),
            'first' => new Route('/foo'),
            'second' => new Route('/foo'),
        ]);

        $ret = $tester->execute(['--format' => 'txt'], ['decorated' => false]);

        $this->assertSame(1, $ret);
        $this->assertStringContainsString('Found 1 error(s) and 1 warning(s) in the routes.', $this->getNormalizedDisplay($tester));
    }

    public function testJsonFormat()
    {
        $tester = $this->createCommandTester([
            'first' => new Route('/foo'),
            'second' => new Route('/foo'),
        ]);

        $ret = $tester->execute(['--format' => 'json'], ['decorated' => false]);

        $this->assertSame(1, $ret);
        $this->assertSame([[
            'type' => 'duplicate',
            'severity' => 'error',
            'route' => 'second',
            'path' => '/foo',
            'previous_route' => 'first',
            'previous_path' => '/foo',
            'message' => 'Route "second" (/foo) has the same path, host, methods, schemes and condition as route "first" (/foo) declared before it and can never match.',
        ]], json_decode($tester->getDisplay(), true, flags: \JSON_THROW_ON_ERROR));
    }

    public function testJsonFormatWithoutIssues()
    {
        $tester = $this->createCommandTester([
            'home' => new Route('/'),
        ]);

        $ret = $tester->execute(['--format' => 'json'], ['decorated' => false]);

        $this->assertSame(0, $ret);
        $this->assertSame([], json_decode($tester->getDisplay(), true, flags: \JSON_THROW_ON_ERROR));
    }

    public function testJsonFormatWithWarningsOnlyReturnsSuccess()
    {
        $tester = $this->createCommandTester([
            'blog_show' => new Route('/blog/{slug}', methods: ['GET']),
            'blog_latest' => new Route('/blog/latest'),
        ]);

        $ret = $tester->execute(['--format' => 'json'], ['decorated' => false]);

        $this->assertSame(0, $ret);
        $issues = json_decode($tester->getDisplay(), true, flags: \JSON_THROW_ON_ERROR);
        $this->assertCount(1, $issues);
        $this->assertSame('warning', $issues[0]['severity']);
    }

    public function testGithubFormat()
    {
        $tester = $this->createCommandTester([
            'blog_show' => new Route('/blog/{slug}', methods: ['GET']),
            'blog_latest' => new Route('/blog/latest'),
            'first' => new Route('/foo'),
            'second' => new Route('/foo'),
        ]);

        $ret = $tester->execute(['--format' => 'github'], ['decorated' => false]);
        $display = $tester->getDisplay();

        $this->assertSame(1, $ret);
        $this->assertStringContainsString('::warning::Route "blog_latest" (/blog/latest) overlaps route "blog_show" (/blog/{slug}) declared before it, which has placeholders without requirements and matches some of its requests.', $display);
        $this->assertStringContainsString('::error::Route "second" (/foo) has the same path, host, methods, schemes and condition as route "first" (/foo) declared before it and can never match.', $display);
    }

    public function testInvalidFormat()
    {
        $tester = $this->createCommandTester([]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Supported formats are "txt", "json", "github".');

        $tester->execute(['--format' => 'xml'], ['decorated' => false]);
    }

    public function testCompleteFormat()
    {
        $tester = new CommandCompletionTester(new RouteLintCommand($this->getRouter([])));

        $this->assertSame(['txt', 'json', 'github'], $tester->complete(['--format', '']));
    }

    private function createCommandTester(array $routes): CommandTester
    {
        $application = new Application();
        $application->addCommand(new RouteLintCommand($this->getRouter($routes)));

        return new CommandTester($application->find('lint:routing'));
    }

    /**
     * @param array<string, Route> $routes
     */
    private function getRouter(array $routes): RouterInterface
    {
        $routeCollection = new RouteCollection();
        foreach ($routes as $name => $route) {
            $routeCollection->add($name, $route);
        }

        $router = $this->createStub(RouterInterface::class);
        $router
            ->method('getRouteCollection')
            ->willReturn($routeCollection);

        return $router;
    }

    private function getNormalizedDisplay(CommandTester $tester): string
    {
        return preg_replace('/\s+/', ' ', str_replace(['[ERROR]', '[WARNING]', '[OK]'], '', $tester->getDisplay()));
    }
}
