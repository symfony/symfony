<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Tests\CacheWarmer;

use PHPUnit\Framework\TestCase;
use Symfony\Component\ExpressionLanguage\CompiledExpressionLanguage;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use Symfony\Component\ExpressionLanguage\SyntaxError;
use Symfony\Component\HttpKernel\Attribute\Cache;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\HttpKernel\CacheWarmer\ExpressionCollector;
use Symfony\Component\HttpKernel\CacheWarmer\ExpressionLanguageCacheWarmer;
use Symfony\Component\HttpKernel\Tests\Fixtures\Controller\ExpressionsController;

class ExpressionLanguageCacheWarmerTest extends TestCase
{
    private string $buildDir;

    protected function setUp(): void
    {
        $this->buildDir = sys_get_temp_dir().'/sf_controller_expressions_'.bin2hex(random_bytes(4));
        mkdir($this->buildDir);
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->buildDir.'/*/*'));
        array_map('rmdir', glob($this->buildDir.'/*'));
        rmdir($this->buildDir);
    }

    public function testWarmUpCompilesTheExpressionsOfControllerAttributes()
    {
        $cacheFile = $this->buildDir.'/expression_language/cache.php';
        $queryFile = $this->buildDir.'/expression_language/query.php';
        $otherFile = $this->buildDir.'/expression_language/other.php';
        $warmer = new ExpressionLanguageCacheWarmer(
            ['cache' => new CompiledExpressionLanguage(new ExpressionLanguage()), 'query' => new CompiledExpressionLanguage(new ExpressionLanguage()), 'other' => new CompiledExpressionLanguage(new ExpressionLanguage())],
            ['cache' => $cacheFile, 'query' => $queryFile, 'other' => $otherFile],
            new ExpressionCollector([ExpressionsController::class], [
                'cache' => [Cache::class => ['if' => [true, null], 'lastModified' => [true, null], 'etag' => [true, null]]],
                'query' => [MapQueryString::class => ['validationGroups' => [false, null]]],
                'other' => [Cache::class => ['if' => [false, null]]],
            ]),
        );

        $this->assertTrue($warmer->isOptional());
        $this->assertSame([$cacheFile, $queryFile, $otherFile], $warmer->warmUp($this->buildDir, $this->buildDir));

        $this->assertSame(['this.lastModified', 'request.isMethodSafe()', 'args["id"]'], array_keys(require $cacheFile));
        $this->assertSame(['"Default"'], array_keys(require $queryFile));
        $this->assertSame([], require $otherFile);

        $expressionLanguage = new CompiledExpressionLanguage(new ExpressionLanguage(), $cacheFile);
        $this->assertSame(123, $expressionLanguage->evaluate('args["id"]', ['request' => null, 'args' => ['id' => 123], 'this' => null]));
    }

    public function testWarmUpCompilesListedExpressions()
    {
        $file = $this->buildDir.'/expression_language/expressions.php';
        $otherFile = $this->buildDir.'/expression_language/other.php';
        $warmer = new ExpressionLanguageCacheWarmer(['app' => new CompiledExpressionLanguage(new ExpressionLanguage()), 'other' => new CompiledExpressionLanguage(new ExpressionLanguage())], ['app' => $file, 'other' => $otherFile], new ExpressionCollector([], [], ['app' => ['request.isSecure()' => [['request'], []]]]));

        $warmer->warmUp($this->buildDir, $this->buildDir);

        $this->assertSame(['request.isSecure()'], array_keys(require $file));
        $this->assertSame([], require $otherFile);
    }

    public function testWarmUpFailsOnInvalidListedExpression()
    {
        $file = $this->buildDir.'/expression_language/expressions.php';
        $warmer = new ExpressionLanguageCacheWarmer(['app' => new CompiledExpressionLanguage(new ExpressionLanguage())], ['app' => $file], new ExpressionCollector([], [], ['app' => ['request.isSecure()' => [null, []], 'a +' => [null, []]]]));

        $this->expectException(SyntaxError::class);
        $this->expectExceptionMessage('Unexpected token "end of expression" of value "" around position 4 for expression `a +`.');

        $warmer->warmUp($this->buildDir, $this->buildDir);
    }

    public function testWarmUpFailsOnUnknownVariableInListedExpression()
    {
        $file = $this->buildDir.'/expression_language/expressions.php';
        $warmer = new ExpressionLanguageCacheWarmer(['app' => new CompiledExpressionLanguage(new ExpressionLanguage())], ['app' => $file], new ExpressionCollector([], [], ['app' => ['request.isSecure()' => [['req'], []]]]));

        $this->expectException(SyntaxError::class);
        $this->expectExceptionMessage('Variable "request" is not valid around position 1 for expression `request.isSecure()`.');

        $warmer->warmUp($this->buildDir, $this->buildDir);
    }

    public function testWarmUpFailsOnUnknownFunctionInListedExpression()
    {
        $file = $this->buildDir.'/expression_language/expressions.php';
        $warmer = new ExpressionLanguageCacheWarmer(['app' => new CompiledExpressionLanguage(new ExpressionLanguage())], ['app' => $file], new ExpressionCollector([], [], ['app' => ['is_granted("ROLE_ADMIN")' => [null, []]]]));

        $this->expectException(SyntaxError::class);
        $this->expectExceptionMessage('The function "is_granted" does not exist around position 1 for expression `is_granted("ROLE_ADMIN")`.');

        $warmer->warmUp($this->buildDir, $this->buildDir);
    }

    public function testWarmUpWithoutBuildDir()
    {
        $file = $this->buildDir.'/expression_language/expressions.php';
        $warmer = new ExpressionLanguageCacheWarmer(['app' => new CompiledExpressionLanguage(new ExpressionLanguage())], ['app' => $file], new ExpressionCollector([ExpressionsController::class], ['app' => [Cache::class => ['etag' => [true, null]]]]));

        $this->assertSame([], $warmer->warmUp($this->buildDir));
        $this->assertFileDoesNotExist($file);
    }
}
