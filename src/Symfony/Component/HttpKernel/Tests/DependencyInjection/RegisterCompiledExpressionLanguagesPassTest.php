<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Tests\DependencyInjection;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Argument\IteratorArgument;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\ExpressionLanguage\CompiledExpressionLanguage;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use Symfony\Component\HttpKernel\Attribute\Cache;
use Symfony\Component\HttpKernel\Attribute\RateLimit;
use Symfony\Component\HttpKernel\CacheWarmer\ExpressionCollector;
use Symfony\Component\HttpKernel\CacheWarmer\ExpressionLanguageCacheWarmer;
use Symfony\Component\HttpKernel\DependencyInjection\RegisterCompiledExpressionLanguagesPass;
use Symfony\Component\HttpKernel\Tests\Fixtures\Controller\CacheAttributeController;
use Symfony\Component\HttpKernel\Tests\Fixtures\Controller\ExpressionsController;

class RegisterCompiledExpressionLanguagesPassTest extends TestCase
{
    public function testProcess()
    {
        $container = self::createContainer(false);
        $container->register('app.expression_language', ExpressionLanguage::class)->addTag('expression_language.compiled');
        $container->register('App\ExpressionLanguage', ExpressionLanguage::class)
            ->setLazy(true)
            ->addTag('expression_language.compiled')
            ->addTag('expression_language.compiled', ['expressions' => ['a', 'b'], 'attributes' => [Cache::class => ['etag']]])
            ->addTag('expression_language.compiled', ['expressions' => ['b', 'c'], 'variables' => ['x'], 'source' => 'tag three', 'attributes' => [RateLimit::class => ['key']], 'string_expressions' => [Cache::class => ['if']]])
            ->addTag('expression_language.compiled', ['expressions' => ['c'], 'variables' => ['x', 'y'], 'source' => 'the configuration'])
            ->addTag('expression_language.compiled', ['attributes' => [RateLimit::class => ['key']], 'variables' => ['z']]);
        $container->register('app.untagged_expression_language', ExpressionLanguage::class);
        $container->register('app.controller', ExpressionsController::class)->addTag('controller.service_arguments');
        $container->register('app.parent_controller', CacheAttributeController::class);
        $container->setDefinition('app.child_controller', new ChildDefinition('app.parent_controller'))->addTag('controller.service_arguments');
        $container->register(ExpressionsController::class, ExpressionsController::class)->addTag('controller.service_arguments');

        (new RegisterCompiledExpressionLanguagesPass())->process($container);

        $file = '%kernel.build_dir%/expression_language/app.expression_language.php';
        $hashedFile = '%kernel.build_dir%/expression_language/'.ContainerBuilder::hash('App\ExpressionLanguage').'.php';
        $decorator = $container->getDefinition('.app.expression_language.compiled');
        $this->assertSame(CompiledExpressionLanguage::class, $decorator->getClass());
        $this->assertSame(['app.expression_language', null, 0], $decorator->getDecoratedService());
        $this->assertEquals([new Reference('.inner'), $file], $decorator->getArguments());
        $this->assertFalse($decorator->isLazy());
        $this->assertSame($hashedFile, $container->getDefinition('.App\ExpressionLanguage.compiled')->getArgument(1));
        $this->assertTrue($container->getDefinition('.App\ExpressionLanguage.compiled')->isLazy());
        $this->assertFalse($container->hasDefinition('.app.untagged_expression_language.compiled'));

        $expressionLanguages = new IteratorArgument([
            'app.expression_language' => new Reference('app.expression_language'),
            'App\ExpressionLanguage' => new Reference('App\ExpressionLanguage'),
        ]);

        $warmer = $container->getDefinition('expression_language.cache_warmer');
        $this->assertEquals($expressionLanguages, $warmer->getArgument(0));
        $this->assertSame(['app.expression_language' => $file, 'App\ExpressionLanguage' => $hashedFile], $warmer->getArgument(1));

        $this->assertEquals($expressionLanguages, $container->getDefinition('console.command.expression_lint')->getArgument(0));

        $collector = $container->getDefinition('expression_language.collector');
        $this->assertSame([ExpressionsController::class, CacheAttributeController::class], $collector->getArgument(0));
        $this->assertSame(['App\ExpressionLanguage' => [
            Cache::class => ['etag' => [false, null], 'if' => [true, ['x']]],
            RateLimit::class => ['key' => [false, ['x', 'z']]],
        ]], $collector->getArgument(1));
        $this->assertSame(['App\ExpressionLanguage' => [
            'a' => [null, []],
            'b' => [null, ['tag three']],
            'c' => [['x', 'y'], ['tag three', 'the configuration']],
        ]], $collector->getArgument(2));
    }

    public function testRemovesTheServicesWhenNoExpressionLanguageIsTagged()
    {
        $container = self::createContainer(false);
        $container->register('app.controller', ExpressionsController::class)->addTag('controller.service_arguments');

        (new RegisterCompiledExpressionLanguagesPass())->process($container);

        $this->assertFalse($container->hasDefinition('expression_language.collector'));
        $this->assertFalse($container->hasDefinition('expression_language.cache_warmer'));
        $this->assertFalse($container->hasDefinition('console.command.expression_lint'));
    }

    public function testRemovesTheWarmerInDebugMode()
    {
        $container = self::createContainer(true);
        $container->register('app.expression_language', ExpressionLanguage::class)->addTag('expression_language.compiled', ['expressions' => ['a'], 'variables' => ['a']]);

        (new RegisterCompiledExpressionLanguagesPass())->process($container);

        $this->assertSame([], $container->getDefinition('app.expression_language')->getArguments());
        $this->assertEquals([new Reference('.inner'), null], $container->getDefinition('.app.expression_language.compiled')->getArguments());
        $this->assertFalse($container->hasDefinition('expression_language.cache_warmer'));
        $this->assertEquals(new IteratorArgument(['app.expression_language' => new Reference('app.expression_language')]), $container->getDefinition('console.command.expression_lint')->getArgument(0));
        $this->assertSame(['app.expression_language' => ['a' => [['a'], []]]], $container->getDefinition('expression_language.collector')->getArgument(2));
    }

    public function testThrowsWhenTheTaggedServiceIsNotAnExpressionLanguage()
    {
        $container = self::createContainer(false);
        $container->register('app.expression_language', \stdClass::class)->addTag('expression_language.compiled');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Service "app.expression_language" tagged "expression_language.compiled" must be an instance of "Symfony\Component\ExpressionLanguage\ExpressionLanguage".');

        (new RegisterCompiledExpressionLanguagesPass())->process($container);
    }

    public function testThrowsWhenTheClassOfTheTaggedServiceCannotBeFound()
    {
        $container = self::createContainer(false);
        $container->register('app.expression_language', 'App\MissingExpressionLanguage')->addTag('expression_language.compiled');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Class "App\MissingExpressionLanguage" used for service "app.expression_language" cannot be found.');

        (new RegisterCompiledExpressionLanguagesPass())->process($container);
    }

    private static function createContainer(bool $debug): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.debug', $debug);
        $container->register('expression_language.collector', ExpressionCollector::class)->setArguments([[], [], []]);
        $container->register('expression_language.cache_warmer', ExpressionLanguageCacheWarmer::class)->setArguments([[], [], new Reference('expression_language.collector')]);
        $container->register('console.command.expression_lint', \stdClass::class)->setArguments([[], new Reference('expression_language.collector')]);

        return $container;
    }
}
