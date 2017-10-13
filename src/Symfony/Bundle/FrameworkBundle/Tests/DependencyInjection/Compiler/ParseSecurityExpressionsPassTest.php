<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests\DependencyInjection\Compiler;

use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\DependencyInjection\Compiler\ParseSecurityExpressionsPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class ParseSecurityExpressionsPassTest extends TestCase
{
    public function testProcess()
    {
        $container = new ContainerBuilder();
        $container->addCompilerPass(new ParseSecurityExpressionsPass());
        $container->register('security.expression_language', 'Symfony\Component\Security\Core\Authorization\ExpressionLanguage');

        $container->register('security.expression.one', 'Symfony\Component\ExpressionLanguage\Expression')
            ->addArgument('true or false')
            ->addTag('security.expression.unparsed');

        $container->register('security.expression.two', 'Symfony\Component\ExpressionLanguage\Expression')
            ->addArgument('false or true')
            ->addTag('security.expression.unparsed');

        $container->compile();

        $expressionOne = $container->getDefinition('security.expression.one');
        $this->assertSame('Symfony\Component\ExpressionLanguage\SerializedParsedExpression', $expressionOne->getClass());
        $this->assertInstanceOf('Symfony\Component\ExpressionLanguage\Node\BinaryNode', unserialize($expressionOne->getArgument(1)));

        $expressionTwo = $container->getDefinition('security.expression.one');
        $this->assertSame('Symfony\Component\ExpressionLanguage\SerializedParsedExpression', $expressionTwo->getClass());
        $this->assertInstanceOf('Symfony\Component\ExpressionLanguage\Node\BinaryNode', unserialize($expressionTwo->getArgument(1)));
    }
}
