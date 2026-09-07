<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\JsonPath\Tests\DependencyInjection;

use PHPUnit\Framework\Attributes\RequiresMethod;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\JsonPath\DependencyInjection\JsonPathPass;
use Symfony\Component\JsonPath\FunctionReturnType;
use Symfony\Component\JsonPath\JsonPathCrawler;
use Symfony\Component\JsonPath\JsonPathCrawlerInterface;

#[RequiresMethod(JsonPathCrawlerInterface::class, 'crawl')]
class JsonPathPassTest extends TestCase
{
    public function testReturnTypeIsReadBackFromTheTag()
    {
        $container = new ContainerBuilder();
        $container->register('json_path.crawler', JsonPathCrawler::class)->setArguments([null, []]);
        $container->register('upper')->addTag('json_path.function', ['name' => 'upper', 'return_type' => 'logical', 'arity' => 1]);

        new JsonPathPass()->process($container);

        $this->assertSame(['upper' => ['arity' => 1, 'return_type' => FunctionReturnType::Logical]], $container->getDefinition('json_path.crawler')->getArgument(1));
    }

    public function testReturnTypeIsKeptWhenTheTagAlreadyHoldsAnEnum()
    {
        $container = new ContainerBuilder();
        $container->register('json_path.crawler', JsonPathCrawler::class)->setArguments([null, []]);
        $container->register('upper')->addTag('json_path.function', ['name' => 'upper', 'return_type' => FunctionReturnType::Nodes, 'arity' => 1]);

        new JsonPathPass()->process($container);

        $this->assertSame(['upper' => ['arity' => 1, 'return_type' => FunctionReturnType::Nodes]], $container->getDefinition('json_path.crawler')->getArgument(1));
    }

    public function testReturnTypeDefaultsToNull()
    {
        $container = new ContainerBuilder();
        $container->register('json_path.crawler', JsonPathCrawler::class)->setArguments([null, []]);
        $container->register('upper')->addTag('json_path.function', ['name' => 'upper', 'arity' => 1]);

        new JsonPathPass()->process($container);

        $this->assertSame(['upper' => ['arity' => 1, 'return_type' => null]], $container->getDefinition('json_path.crawler')->getArgument(1));
    }
}
