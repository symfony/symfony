<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Routing\Generator\AstGenerator;

use PhpParser\BuilderFactory;
use PhpParser\Comment;
use PhpParser\Node\Name;
use PhpParser\Node\Param;
use PhpParser\Node\Stmt;
use PhpParser\Node\Expr;
use Psr\Log\LoggerInterface;
use Symfony\Component\AstGenerator\AstGeneratorInterface;
use Symfony\Component\AstGenerator\Util\AstHelper;
use Symfony\Component\Routing\Exception\RouteNotFoundException;
use Symfony\Component\Routing\Generator\UrlGenerator;
use Symfony\Component\Routing\RequestContext;

/**
 * @author Guilhem N. <egetick@gmail.com>
 */
class UrlGeneratorGenerator implements AstGeneratorInterface
{
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
            'class' => 'ProjectUrlGenerator',
            'base_class' => UrlGenerator::class,
        ), $context);

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
            ->addStmt($this->factory->property('declaredRoutes')->makePrivate()->makeStatic())
            ->addStmt($this->generateConstructor($object, $context))
            ->addStmt($this->generateGenerateMethod($object, $context));
    }

    private function generateConstructor($object, array $context)
    {
        $constructor = $this->factory->method('__construct')
            ->makePublic()
            ->addParam($this->factory->param('context')->setTypeHint(RequestContext::class))
            ->addParam($this->factory->param('logger')->setTypeHint(LoggerInterface::class)->setDefault(null));

        $code = <<<'EOF'
$this->context = $context;
$this->logger = $logger;
EOF;
        foreach (AstHelper::raw($code) as $stmt) {
            $constructor->addStmt($stmt);
        }

        $constructor->addStmt(new Stmt\If_(
            new Expr\BinaryOp\Equal(
                AstHelper::value(null),
                new Expr\StaticPropertyFetch(new Name('self'), 'declaredRoutes')
            ),
            array(
                'stmts' => array(
                    new Expr\Assign(
                        new Expr\StaticPropertyFetch(new Name('self'), 'declaredRoutes'),
                        $this->generateDeclaredRoutes($object, $context)
                    ),
                ),
            )
        ));

        return $constructor;
    }

    /**
     * Generates an AST node representing an array of defined routes
     * together with the routes properties (e.g. requirements).
     *
     * @return Expr\Array_
     */
    private function generateDeclaredRoutes($object, array $context)
    {
        $routes = array();
        foreach ($object->all() as $name => $route) {
            $compiledRoute = $route->compile();

            $properties = array();
            $properties[] = $compiledRoute->getVariables();
            $properties[] = $route->getDefaults();
            $properties[] = $route->getRequirements();
            $properties[] = $compiledRoute->getTokens();
            $properties[] = $compiledRoute->getHostTokens();
            $properties[] = $route->getSchemes();

            $routes[$name] = $properties;
        }

        return AstHelper::value($routes);
    }

    /**
     * Generates an AST node representing the `generate` method that implements the UrlGeneratorInterface.
     *
     * @return string PHP code
     */
    private function generateGenerateMethod()
    {
        $generateMethod = $this->factory
            ->method('generate')
            ->makePublic()
            ->addParam($this->factory->param('name'))
            ->addParam($this->factory->param('parameters')->setDefault(array()))
            ->addParam($this->factory->param('referenceType')->setDefault(UrlGenerator::ABSOLUTE_PATH));

        $exception = RouteNotFoundException::class;
        $code = <<<EOF
if (!isset(self::\$declaredRoutes[\$name])) {
    throw new {$exception}(sprintf('Unable to generate a URL for the named route "%s" as such route does not exist.', \$name));
}

list(\$variables, \$defaults, \$requirements, \$tokens, \$hostTokens, \$requiredSchemes) = self::\$declaredRoutes[\$name];

return \$this->doGenerate(\$variables, \$defaults, \$requirements, \$tokens, \$parameters, \$name, \$referenceType, \$hostTokens, \$requiredSchemes);
EOF;

        foreach (AstHelper::raw($code) as $stmt) {
            $generateMethod->addStmt($stmt);
        }

        return $generateMethod;
    }
}
