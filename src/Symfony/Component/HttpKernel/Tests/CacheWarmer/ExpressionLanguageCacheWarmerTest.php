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
use Symfony\Component\HttpKernel\Attribute\Cache;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
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
            [$cacheFile => new CompiledExpressionLanguage(new ExpressionLanguage()), $queryFile => new CompiledExpressionLanguage(new ExpressionLanguage()), $otherFile => new CompiledExpressionLanguage(new ExpressionLanguage())],
            [ExpressionsController::class],
            [$cacheFile => [Cache::class => ['if' => true, 'lastModified' => true, 'etag' => true]], $queryFile => [MapQueryString::class => ['validationGroups' => false]], $otherFile => [Cache::class => ['if' => false]]],
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
        $warmer = new ExpressionLanguageCacheWarmer([$file => new CompiledExpressionLanguage(new ExpressionLanguage()), $otherFile => new CompiledExpressionLanguage(new ExpressionLanguage())], [], [], [$file => ['request.isSecure()', 'a +']]);

        $warmer->warmUp($this->buildDir, $this->buildDir);

        $this->assertSame(['request.isSecure()'], array_keys(require $file));
        $this->assertSame([], require $otherFile);
    }

    public function testWarmUpWithoutBuildDir()
    {
        $file = $this->buildDir.'/expression_language/expressions.php';
        $warmer = new ExpressionLanguageCacheWarmer([$file => new CompiledExpressionLanguage(new ExpressionLanguage())], [ExpressionsController::class], [$file => [Cache::class => ['etag' => true]]]);

        $this->assertSame([], $warmer->warmUp($this->buildDir));
        $this->assertFileDoesNotExist($file);
    }
}
