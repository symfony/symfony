<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Attribute;

use Symfony\Component\DependencyInjection\Argument\ArgumentInterface;
use Symfony\Component\DependencyInjection\Exception\LogicException;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\ExpressionLanguage\Expression;

/**
 * Attribute to tell a parameter how to be autowired.
 *
 * @author Kevin Bond <kevinbond@gmail.com>
 */
#[\Attribute(\Attribute::TARGET_PARAMETER)]
class Autowire
{
    public readonly string|array|Expression|Reference|ArgumentInterface|null $value;
    public readonly bool|array $lazy;

    /**
     * Use only ONE of the following.
     *
     * @param string|array|ArgumentInterface|null $value      Value to inject (ie "%kernel.project_dir%/some/path")
     * @param string|null                         $service    Service ID (ie "some.service")
     * @param string|null                         $expression Expression (ie 'service("some.service").someMethod()')
     * @param string|null                         $env        Environment variable name (ie 'SOME_ENV_VARIABLE')
     * @param string|null                         $param      Parameter name (ie 'some.parameter.name')
     * @param bool|class-string|class-string[]    $lazy       Whether to use lazy-loading for this argument
     */
    public function __construct(
        string|array|ArgumentInterface|null $value = null,
        ?string $service = null,
        ?string $expression = null,
        ?string $env = null,
        ?string $param = null,
        bool|string|array $lazy = false,
    ) {
        if ($this->lazy = \is_string($lazy) ? [$lazy] : $lazy) {
            if (null !== ($expression ?? $env ?? $param)) {
                throw new LogicException('#[Autowire] attribute cannot be $lazy and use $expression, $env, or $param.');
            }
            if (null !== $value && null !== $service) {
                throw new LogicException('#[Autowire] attribute cannot declare $value and $service at the same time.');
            }
        } elseif (!(null !== $value xor null !== $service xor null !== $expression xor null !== $env xor null !== $param)) {
            throw new LogicException('#[Autowire] attribute must declare exactly one of $service, $expression, $env, $param or $value.');
        }

        if (\is_string($value) && str_starts_with($value, '@')) {
            match (true) {
                str_starts_with($value, '@@') => $value = substr($value, 1),
                str_starts_with($value, '@=') => $expression = substr($value, 2),
                default => $service = substr($value, 1),
            };
        }

        $this->value = match (true) {
            null !== $service => new Reference($service),
            null !== $expression => class_exists(Expression::class) ? new Expression($expression) : throw new LogicException('Unable to use expressions as the Symfony ExpressionLanguage component is not installed. Try running "composer require symfony/expression-language".'),
            null !== $env => "%env($env)%",
            null !== $param => "%$param%",
            default => $value,
        };
    }
}
