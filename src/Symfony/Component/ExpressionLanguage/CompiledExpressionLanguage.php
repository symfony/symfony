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

/**
 * Decorates an expression language to evaluate and lint the expressions compiled by dumpCompiled() without parsing them.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
final class CompiledExpressionLanguage extends ExpressionLanguage
{
    /**
     * @var array<string, array{callable(array<string, mixed>, array<string, array>=): mixed, array<string, int|null>, bool, bool}>
     */
    private array $compiledExpressions;
    private string|false $compiledExpressionsPath = false;

    /**
     * @param string|null $compiledExpressionsFile A file generated with dumpCompiled()
     */
    public function __construct(
        private ExpressionLanguage $expressionLanguage,
        private ?string $compiledExpressionsFile = null,
    ) {
        // the parent constructor is not called: every method delegates to the decorated expression language
    }

    public function compile(Expression|string $expression, array $names = []): string
    {
        return $this->expressionLanguage->compile($expression, $names);
    }

    public function evaluate(Expression|string $expression, array $values = []): mixed
    {
        if (null === $compiled = $this->getCompiledExpression($expression)) {
            return $this->expressionLanguage->evaluate($expression, $values);
        }

        [$evaluator, $variables, $usesFunctions, $hasCalls] = $compiled;

        // the expression was compiled without knowing which variables would be valid:
        // when one is missing, parsing it again reports the error the way it always did
        foreach ($variables as $name => $cursor) {
            if (null === $cursor) {
                break;
            }

            if (!\array_key_exists($name, $values)) {
                return $this->expressionLanguage->evaluate($expression, $values);
            }
        }

        // an error raised by the compiled code is reported the way evaluating the nodes reports it,
        // unless evaluating them would call again the methods and functions that the compiled code called;
        // deprecations don't change the result, so they're reported as is
        $path = $this->compiledExpressionsPath;
        $previous = set_error_handler(static function (int $type, string $message, string $file = '', int $line = 0) use (&$previous, $path): bool {
            if ($path === $file && !((\E_DEPRECATED | \E_USER_DEPRECATED) & $type)) {
                throw new \ErrorException($message, 0, $type, $file, $line);
            }

            return null !== $previous && false !== $previous($type, $message, $file, $line);
        });

        try {
            return $usesFunctions ? $evaluator($values, $this->expressionLanguage->functions) : $evaluator($values);
        } catch (\Throwable $e) {
            if ($hasCalls || $path !== $e->getFile()) {
                throw $e;
            }
        } finally {
            restore_error_handler();
        }

        return $this->expressionLanguage->evaluate($expression, $values);
    }

    public function parse(Expression|string $expression, array $names, int $flags = 0): ParsedExpression
    {
        return $this->expressionLanguage->parse($expression, $names, $flags);
    }

    public function lint(Expression|string $expression, array $names, int $flags = 0): void
    {
        if ($expression instanceof ParsedExpression || null === $compiled = $this->getCompiledExpression($expression)) {
            $this->expressionLanguage->lint($expression, $names, $flags);

            return;
        }

        if ($flags & Parser::IGNORE_UNKNOWN_VARIABLES) {
            return;
        }

        // variables are sorted by the position where they're first read without "??", like the parser meets them
        foreach ($compiled[1] as $name => $cursor) {
            if (null === $cursor) {
                return;
            }

            if (!\in_array($name, $names, true)) {
                throw new SyntaxError(\sprintf('Variable "%s" is not valid.', $name), $cursor, (string) $expression, $name, $names);
            }
        }
    }

    /**
     * Compiles expressions to the PHP code of a file that the constructor can load.
     *
     * The expressions this file holds are then evaluated and linted without being parsed.
     * A function whose compiler throws is called through its evaluator.
     * The expressions that cannot be compiled are left out.
     *
     * @param iterable<Expression|string> $expressions
     */
    public function dumpCompiled(iterable $expressions): string
    {
        $parser = new Parser($this->expressionLanguage->functions);
        $methods = $map = [];

        // placeholders for the parameters of the generated methods, renamed once the variables of each expression are known
        $valuesPlaceholder = 'values'.bin2hex(random_bytes(8));
        $functionsPlaceholder = 'functions'.bin2hex(random_bytes(8));
        $functions = [];

        foreach ($this->expressionLanguage->functions as $name => $function) {
            $compile = $function['compiler'];
            $function['compiler'] = static function (...$arguments) use ($compile, $name, $valuesPlaceholder, $functionsPlaceholder): string {
                try {
                    return $compile(...$arguments);
                } catch (\Exception) {
                    return \sprintf('$%s[%s][\'evaluator\']($%s%s)', $functionsPlaceholder, var_export($name, true), $valuesPlaceholder, $arguments ? ', '.implode(', ', $arguments) : '');
                }
            };
            $functions[$name] = $function;
        }

        $compiler = new Compiler($functions);
        $lexer = new Lexer();

        foreach ($expressions as $expression) {
            if (isset($map[$expression = (string) $expression])) {
                continue;
            }

            try {
                $nodes = $parser->parse($lexer->tokenize($expression), [], Parser::IGNORE_UNKNOWN_VARIABLES);
                $variables = $parser->getVariables();
                uasort($variables, static fn (?int $a, ?int $b) => ($a ?? \PHP_INT_MAX) <=> ($b ?? \PHP_INT_MAX));
                $thisVariable = null;

                if (\array_key_exists('this', $variables)) {
                    $thisVariable = 'this_';
                    while (\array_key_exists($thisVariable, $variables)) {
                        $thisVariable .= '_';
                    }
                    self::renameVariable($nodes, 'this', $thisVariable);
                }

                $source = $compiler->reset()->compile($nodes)->getSource();
            } catch (\Exception) {
                continue;
            }

            $phpVariables = [];
            foreach (token_get_all('<?php '.$source) as $token) {
                if (\is_array($token) && \T_VARIABLE === $token[0]) {
                    $phpVariables[substr($token[1], 1)] = true;
                }
            }

            if (isset($phpVariables['this'])) {
                continue;
            }

            $usesFunctions = isset($phpVariables[$functionsPlaceholder]);
            unset($phpVariables[$valuesPlaceholder], $phpVariables[$functionsPlaceholder]);

            $param = 'values';
            while (isset($phpVariables[$param])) {
                $param .= '_';
            }
            $params = 'array $'.$param;
            $source = str_replace('$'.$valuesPlaceholder, '$'.$param, $source);

            if ($usesFunctions) {
                $functionsParam = 'functions';
                while (isset($phpVariables[$functionsParam])) {
                    $functionsParam .= '_';
                }
                $params .= ', array $'.$functionsParam;
                $source = str_replace('$'.$functionsPlaceholder, '$'.$functionsParam, $source);
            }

            $method = 'e'.\count($methods);
            $code = '';
            foreach ($phpVariables as $name => $_) {
                $code .= \sprintf("            \$%s = \$%s[%s] ?? null;\n", $name, $param, var_export($thisVariable === $name ? 'this' : $name, true));
            }

            $methods[$method] = \sprintf("        public static function %s(%s): mixed\n        {\n%s            return %s;\n        }\n", $method, $params, $code ? $code."\n" : '', $source);
            $map[$expression] = [$method, $variables, $usesFunctions, self::hasCalls($nodes)];
        }

        if (!$map) {
            return "<?php\n\nreturn [];\n";
        }

        $namespace = 'ExpressionLanguage'.substr(hash('xxh128', $methods = implode("\n", $methods)), 0, 16);
        $code = "<?php\n\n// This file has been auto-generated by the Symfony ExpressionLanguage component.\n\nnamespace {$namespace};\n\n";
        $code .= "if (!\\class_exists(CompiledExpressions::class, false)) {\n    final class CompiledExpressions\n    {\n{$methods}    }\n}\n\nreturn [\n";

        foreach ($map as $expression => [$method, $variables, $usesFunctions, $hasCalls]) {
            $code .= \sprintf("    %s => [%s, %s, %s, %s],\n", var_export($expression, true), var_export($namespace.'\\CompiledExpressions::'.$method, true), self::exportVariables($variables), $usesFunctions ? 'true' : 'false', $hasCalls ? 'true' : 'false');
        }

        return $code."];\n";
    }

    public function register(string $name, callable $compiler, callable $evaluator): void
    {
        $this->expressionLanguage->register($name, $compiler, $evaluator);
    }

    public function addFunction(ExpressionFunction $function): void
    {
        $this->expressionLanguage->addFunction($function);
    }

    public function registerProvider(ExpressionFunctionProviderInterface $provider): void
    {
        $this->expressionLanguage->registerProvider($provider);
    }

    /**
     * @return array{callable(array<string, mixed>, array<string, array>=): mixed, array<string, int|null>, bool, bool}|null
     */
    private function getCompiledExpression(Expression|string $expression): ?array
    {
        if (!isset($this->compiledExpressions)) {
            $this->compiledExpressions = [];

            if (null !== $this->compiledExpressionsFile && is_file($this->compiledExpressionsFile)) {
                $this->compiledExpressions = require $this->compiledExpressionsFile;

                // the class may have been declared by another file with the same content: errors are raised from that file
                if ($compiled = reset($this->compiledExpressions)) {
                    $this->compiledExpressionsPath = \ReflectionMethod::createFromMethodName($compiled[0])->getFileName();
                }
            }
        }

        return $this->compiledExpressions[(string) $expression] ?? null;
    }

    private static function renameVariable(Node\Node $node, string $from, string $to): void
    {
        if ($node instanceof Node\NameNode && $from === $node->attributes['name']) {
            $node->attributes['name'] = $to;
        }

        foreach ($node->nodes as $child) {
            self::renameVariable($child, $from, $to);
        }
    }

    private static function hasCalls(Node\Node $node): bool
    {
        if ($node instanceof Node\FunctionNode || ($node instanceof Node\GetAttrNode && Node\GetAttrNode::METHOD_CALL === $node->attributes['type'])) {
            return true;
        }

        foreach ($node->nodes as $child) {
            if (self::hasCalls($child)) {
                return true;
            }
        }

        return false;
    }

    private static function exportVariables(array $variables): string
    {
        $code = [];
        foreach ($variables as $name => $cursor) {
            $code[] = var_export($name, true).' => '.var_export($cursor, true);
        }

        return '['.implode(', ', $code).']';
    }
}
