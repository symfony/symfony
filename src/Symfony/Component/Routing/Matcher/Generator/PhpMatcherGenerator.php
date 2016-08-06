<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Routing\Matcher\Generator;

use PhpParser\BuilderFactory;
use PhpParser\Comment;
use PhpParser\Node\Arg;
use PhpParser\Node\Name;
use PhpParser\Node\Param;
use PhpParser\Node\Stmt;
use PhpParser\Node\Expr;
use PhpParser\Node\Scalar;
use Symfony\Component\AstGenerator\AstGeneratorInterface;
use Symfony\Component\AstGenerator\Util\AstHelper;
use Symfony\Component\ExpressionLanguage\ExpressionFunctionProviderInterface;
use Symfony\Component\Routing\Exception\MethodNotAllowedException;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Symfony\Component\Routing\RequestContext;

/**
 * @author Guilhem N. <egetick@gmail.com>
 */
class PhpMatcherGenerator implements AstGeneratorInterface
{
    private $expressionLanguage;

    /**
     * @var ExpressionFunctionProviderInterface[]
     */
    private $expressionLanguageProviders = array();
    private $factory;

    public function __construct()
    {
        $this->factory = new BuilderFactory();
    }

    /**
     * {@inheritdoc}
     */
    public function generate($object, array $context = array())
    {
        $context = array_replace(array(
            'class' => 'ProjectUrlMatcher',
            'base_class' => 'Symfony\\Component\\Routing\\Matcher\\UrlMatcher',
        ), $context);

        // trailing slash support is only enabled if we know how to redirect the user
        $interfaces = class_implements($context['base_class']);
        $supportsRedirections = isset($interfaces['Symfony\\Component\\Routing\\Matcher\\RedirectableUrlMatcherInterface']);

        return array($this->generateClass($object, $context)->getNode());
    }

    /**
     * {@inheritdoc}
     */
    public function supportsGeneration($object)
    {
        return is_string($object) && class_exists($object);
    }

    private function generateClass($object, array $context)
    {
        $docComment = <<<COMMENT
/**
 * {$context['class']}.
 *
 * This class has been auto-generated
 * by the Symfony Routing Component.
 */
COMMENT;

        return $this->factory->class($context['class'])
            ->setDocComment($docComment)
            ->extend($context['base_class'])
            ->addStmt($this->factory->property('context')->makePrivate())
            ->addStmt(
                $this->factory->method('__construct')
                    ->makePublic()
                    ->addParam($this->factory->param('context')->setTypeHint(RequestContext::class))
                    ->addStmt(
                        // $this->context = $context
                        new Expr\Assign(
                            new Expr\PropertyFetch(new Expr\Variable('this'), 'context'),
                            new Expr\Variable('context')
                        )
                    )
            )
            ->addStmt($this->generateMatchMethod($object, $context));
    }

    private function generateMatchMethod($object, array $context)
    {
        $method = $this->factory
            ->method('match')
            ->makePublic()
            ->addParam($this->factory->param('pathinfo'))
            ->addStmt(new Expr\Assign(new Expr\Variable('allow'), AstHelper::value(array())))
            ->addStmt(new Expr\Assign(
                new Expr\Variable('pathinfo'),
                new Expr\FuncCall(new Name('rawurldecode'), array(
                    new Arg(new Expr\Variable('pathinfo')),
                ))
            ))
            ->addStmt(new Expr\Assign(new Expr\Variable('context'), new Expr\PropertyFetch(new Expr\Variable('this'), 'context')))
            ->addStmt(new Expr\Assign(new Expr\Variable('request'), new Expr\PropertyFetch(new Expr\Variable('this'), 'request')));

        $method->addStmt(new Stmt\Throw_(new Expr\Ternary(
            new Expr\BinaryOp\Smaller(
                new Scalar\LNumber(0),
                new Expr\FuncCall(new Name('count'), array(new Arg(new Expr\Variable('allow'))))
            ),
            new Expr\New_(new Name(MethodNotAllowedException::class), array(
                new Arg(new Expr\FuncCall(new Name('array_unique'), array(
                    new Arg(new Expr\Variable('allow')),
                ))),
            )),
            new Expr\New_(new Name(ResourceNotFoundException::class))
        )));

        return $method;
    }
}
