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
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\ExpressionLanguage\CompiledExpressionLanguage;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use Symfony\Component\HttpKernel\Attribute\Cache;
use Symfony\Component\HttpKernel\Attribute\RateLimit;
use Symfony\Component\HttpKernel\CacheWarmer\ExpressionLanguageCacheWarmer;
use Symfony\Component\HttpKernel\DependencyInjection\RegisterCompiledExpressionLanguagesPass;
use Symfony\Component\HttpKernel\Tests\Fixtures\Controller\CacheAttributeController;
use Symfony\Component\HttpKernel\Tests\Fixtures\Controller\ExpressionsController;

class RegisterCompiledExpressionLanguagesPassTest extends TestCase
{
    public function testProcess()
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.debug', false);
        $container->register('expression_language.cache_warmer', ExpressionLanguageCacheWarmer::class)->setArguments([[], [], [], []]);
        $container->register('app.expression_language', ExpressionLanguage::class)->addTag('expression_language.compiled');
        $container->register('App\ExpressionLanguage', ExpressionLanguage::class)
            ->setLazy(true)
            ->addTag('expression_language.compiled')
            ->addTag('expression_language.compiled', ['expressions' => ['a', 'b'], 'attributes' => [Cache::class => ['etag']]])
            ->addTag('expression_language.compiled', ['expressions' => ['b', 'c'], 'attributes' => [RateLimit::class => ['key']], 'string_expressions' => [Cache::class => ['if']]]);
        $container->register('app.untagged_expression_language', ExpressionLanguage::class);
        $container->register('app.controller', ExpressionsController::class)->addTag('controller.service_arguments');
        $container->register('app.parent_controller', CacheAttributeController::class);
        $container->setDefinition('app.child_controller', new ChildDefinition('app.parent_controller'))->addTag('controller.service_arguments');
        $container->register(ExpressionsController::class, ExpressionsController::class)->addTag('controller.service_arguments');

        (new RegisterCompiledExpressionLanguagesPass())->process($container);

        $hashedFile = '%kernel.build_dir%/expression_language/'.ContainerBuilder::hash('App\ExpressionLanguage').'.php';
        $decorator = $container->getDefinition('.app.expression_language.compiled');
        $this->assertSame(CompiledExpressionLanguage::class, $decorator->getClass());
        $this->assertSame(['app.expression_language', null, 0], $decorator->getDecoratedService());
        $this->assertEquals([new Reference('.inner'), '%kernel.build_dir%/expression_language/app.expression_language.php'], $decorator->getArguments());
        $this->assertFalse($decorator->isLazy());
        $this->assertSame($hashedFile, $container->getDefinition('.App\ExpressionLanguage.compiled')->getArgument(1));
        $this->assertTrue($container->getDefinition('.App\ExpressionLanguage.compiled')->isLazy());
        $this->assertFalse($container->hasDefinition('.app.untagged_expression_language.compiled'));

        $warmer = $container->getDefinition('expression_language.cache_warmer');
        $this->assertEquals(new IteratorArgument([
            '%kernel.build_dir%/expression_language/app.expression_language.php' => new Reference('app.expression_language'),
            $hashedFile => new Reference('App\ExpressionLanguage'),
        ]), $warmer->getArgument(0));
        $this->assertSame([ExpressionsController::class, CacheAttributeController::class], $warmer->getArgument(1));
        $this->assertSame([$hashedFile => [Cache::class => ['etag' => false, 'if' => true], RateLimit::class => ['key' => false]]], $warmer->getArgument(2));
        $this->assertSame([$hashedFile => ['a', 'b', 'c']], $warmer->getArgument(3));
    }

    public function testRemovesTheWarmerWhenNoExpressionLanguageIsTagged()
    {
        $container = new ContainerBuilder();
        $container->register('expression_language.cache_warmer', ExpressionLanguageCacheWarmer::class)->setArguments([[], [], [], []]);
        $container->register('app.controller', ExpressionsController::class)->addTag('controller.service_arguments');

        (new RegisterCompiledExpressionLanguagesPass())->process($container);

        $this->assertFalse($container->hasDefinition('expression_language.cache_warmer'));
    }

    public function testRemovesTheWarmerInDebugMode()
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.debug', true);
        $container->register('expression_language.cache_warmer', ExpressionLanguageCacheWarmer::class)->setArguments([[], [], [], []]);
        $container->register('app.expression_language', ExpressionLanguage::class)->addTag('expression_language.compiled');

        (new RegisterCompiledExpressionLanguagesPass())->process($container);

        $this->assertSame([], $container->getDefinition('app.expression_language')->getArguments());
        $this->assertEquals([new Reference('.inner'), null], $container->getDefinition('.app.expression_language.compiled')->getArguments());
        $this->assertFalse($container->hasDefinition('expression_language.cache_warmer'));
    }

    public function testSkipsExpressionLanguagesThatCannotLoadCompiledExpressions()
    {
        $container = new ContainerBuilder();
        $container->register('expression_language.cache_warmer', ExpressionLanguageCacheWarmer::class)->setArguments([[], [], [], []]);
        $container->register('app.expression_language', \stdClass::class)->addTag('expression_language.compiled');

        (new RegisterCompiledExpressionLanguagesPass())->process($container);

        $this->assertFalse($container->hasDefinition('.app.expression_language.compiled'));
        $this->assertFalse($container->hasDefinition('expression_language.cache_warmer'));
    }
}
