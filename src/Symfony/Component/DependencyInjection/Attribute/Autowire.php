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
    public readonly string|array|Expression|Reference $value;

    /**
     * Use only ONE of the following.
     *
     * @param string|null $value      Parameter value (ie "%kernel.project_dir%/some/path")
     * @param string|null $service    Service ID (ie "some.service")
     * @param string|null $expression Expression (ie 'service("some.service").someMethod()')
     */
    public function __construct(
        string|array $value = null,
        string $service = null,
        string $expression = null,
    ) {
        if (!($service xor $expression xor null !== $value)) {
            throw new LogicException('#[Autowire] attribute must declare exactly one of $service, $expression, or $value.');
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
            null !== $value => $value,
        };
    }
}
