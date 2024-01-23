<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\ExpressionLanguage;

use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

// Help opcache.preload discover always-needed symbols
class_exists(ParsedExpression::class);

/**
 * Allows to compile and evaluate expressions written in your own DSL.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class ExpressionLanguage
{
    private CacheItemPoolInterface $cache;
    private Lexer $lexer;
    private Parser $parser;
    private Compiler $compiler;

    protected array $functions = [];

    /**
     * @param ExpressionFunctionProviderInterface[] $providers
     */
    public function __construct(?CacheItemPoolInterface $cache = null, array $providers = [])
    {
        $this->cache = $cache ?? new ArrayAdapter();
        $this->registerFunctions();
        foreach ($providers as $provider) {
            $this->registerProvider($provider);
        }
    }

    /**
     * Compiles an expression source code.
     */
    public function compile(Expression|string $expression, array $names = []): string
    {
        return $this->getCompiler()->compile($this->parse($expression, $names)->getNodes())->getSource();
    }

    /**
     * Evaluate an expression.
     */
    public function evaluate(Expression|string $expression, array $values = []): mixed
    {
        return $this->parse($expression, array_keys($values))->getNodes()->evaluate($this->functions, $values);
    }

    /**
     * Parses an expression.
     */
    public function parse(Expression|string $expression, array $names): ParsedExpression
    {
        if ($expression instanceof ParsedExpression) {
            return $expression;
        }

        asort($names);
        $cacheKeyItems = [];

        foreach ($names as $nameKey => $name) {
            $cacheKeyItems[] = \is_int($nameKey) ? $name : $nameKey.':'.$name;
        }

        $cacheItem = $this->cache->getItem(rawurlencode($expression.'//'.implode('|', $cacheKeyItems)));

        if (null === $parsedExpression = $cacheItem->get()) {
            $nodes = $this->getParser()->parse($this->getLexer()->tokenize((string) $expression), $names);
            $parsedExpression = new ParsedExpression((string) $expression, $nodes);

            $cacheItem->set($parsedExpression);
            $this->cache->save($cacheItem);
        }

        return $parsedExpression;
    }

    /**
     * Validates the syntax of an expression.
     *
     * @param array|null $names The list of acceptable variable names in the expression, or null to accept any names
     *
     * @throws SyntaxError When the passed expression is invalid
     */
    public function lint(Expression|string $expression, ?array $names): void
    {
        if ($expression instanceof ParsedExpression) {
            return;
        }

        $this->getParser()->lint($this->getLexer()->tokenize((string) $expression), $names);
    }

    /**
     * Registers a function.
     *
     * @param callable $compiler  A callable able to compile the function
     * @param callable $evaluator A callable able to evaluate the function
     *
     * @return void
     *
     * @throws \LogicException when registering a function after calling evaluate(), compile() or parse()
     *
     * @see ExpressionFunction
     */
    public function register(string $name, callable $compiler, callable $evaluator)
    {
        if (isset($this->parser)) {
            throw new \LogicException('Registering functions after calling evaluate(), compile() or parse() is not supported.');
        }

        $this->functions[$name] = ['compiler' => $compiler, 'evaluator' => $evaluator];
    }

    /**
     * @return void
     */
    public function addFunction(ExpressionFunction $function)
    {
        $this->register($function->getName(), $function->getCompiler(), $function->getEvaluator());
    }

    /**
     * @return void
     */
    public function registerProvider(ExpressionFunctionProviderInterface $provider)
    {
        foreach ($provider->getFunctions() as $function) {
            $this->addFunction($function);
        }
    }

    /**
     * @return void
     */
    protected function registerFunctions()
    {
        $this->addFunction(ExpressionFunction::fromPhp('constant'));

        $this->addFunction(new ExpressionFunction('enum',
            static fn ($str): string => sprintf("(\constant(\$v = (%s))) instanceof \UnitEnum ? \constant(\$v) : throw new \TypeError(\sprintf('The string \"%%s\" is not the name of a valid enum case.', \$v))", $str),
            static function ($arguments, $str): \UnitEnum {
                $value = \constant($str);

                if (!$value instanceof \UnitEnum) {
                    throw new \TypeError(sprintf('The string "%s" is not the name of a valid enum case.', $str));
                }

                return $value;
            }
        ));
    }

    private function getLexer(): Lexer
    {
        return $this->lexer ??= new Lexer();
    }

    private function getParser(): Parser
    {
        return $this->parser ??= new Parser($this->functions);
    }

    private function getCompiler(): Compiler
    {
        $this->compiler ??= new Compiler($this->functions);

        return $this->compiler->reset();
    }
}
