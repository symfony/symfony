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
use Symfony\Component\Ast\NodeList;
use Symfony\Component\Ast\Util\AstHelper;
use Symfony\Component\Routing\Exception\RouteNotFoundException;
use Symfony\Component\Routing\Generator\UrlGenerator;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\RouteCollection;

/**
 * @author Guilhem N. <egetick@gmail.com>
 */
final class GeneratorAstGenerator implements GeneratorAstGeneratorInterface
{
    public function __construct(RouteCollection $routes)
    {
        $this->routes = $routes;
        $this->factory = new BuilderFactory();
    }

    /**
     * {@inheritdoc}
     */
    public function generate(array $options = array())
    {
        $options = array_replace(array(
            'class' => 'ProjectUrlGenerator',
            'base_class' => UrlGenerator::class,
        ), $options);

        return new NodeList(array($this->generateClass($options)->getNode()));
    }

    private function generateClass(array $options)
    {
        $docComment = <<<COMMENT
/**
 * {$options['class']}.
 *
 * This class has been auto-generated
 * by the Symfony Routing Component.
 */
COMMENT;

        return $this->factory->class($options['class'])
            ->setDocComment($docComment)
            ->extend($options['base_class'])
            ->addStmt($this->factory->property('declaredRoutes')->makePrivate()->makeStatic())
            ->addStmt($this->generateConstructor())
            ->addStmt($this->generateGenerateMethod());
    }

    private function generateConstructor()
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
                        $this->generateDeclaredRoutes()
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
    private function generateDeclaredRoutes()
    {
        $routes = array();
        foreach ($this->routes->all() as $name => $route) {
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
