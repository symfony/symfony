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

/**
 * An attribute to tell how a method is to be call.
 *
 * @author Aleksey Polyvanyi <aleksey.polyvanyi@eonx.com>
 */
#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::IS_REPEATABLE)]
class AutoconfigureCall extends Autoconfigure
{
    use Traits\ValueTrait;

    /**
     * Use only ONE of the following.
     *
     * @param string|array|null $value      Parameter value (ie "%kernel.project_dir%/some/path")
     * @param string|null       $service    Service ID (ie "some.service")
     * @param string|null       $expression Expression (ie 'service("some.service").someMethod()')
     * @param string|null       $env        Environment variable name (ie 'SOME_ENV_VARIABLE')
     * @param string|null       $param      Parameter name (ie 'some.parameter.name')
     */
    public function __construct(
        string $name,
        string|array $value = null,
        string $service = null,
        string $expression = null,
        string $env = null,
        string $param = null,
    )
    {
        parent::__construct(
            calls: [
                [$name => [$this->normalizeValue($value, $service, $expression, $env, $param)]],
            ]
        );
    }
}
