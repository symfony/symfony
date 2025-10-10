<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Config;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\Loader\Configurator\ReferenceConfigurator;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\ExpressionLanguage\Expression;

require_once __DIR__.\DIRECTORY_SEPARATOR.'functions.php';

/**
 * @psalm-type Arguments = list<mixed>|array<string, mixed>
 * @psalm-type Callback = string|array{0:string|Reference|ReferenceConfigurator,1:string}|\Closure|Reference|ReferenceConfigurator|Expression
 * @psalm-type Tags = list<string|array<string, array<string, mixed>>>
 * @psalm-type Deprecation = array{package: string, version: string, message?: string}
 * @psalm-type Call = array<string, Arguments>|array{0:string, 1?:Arguments, 2?:bool}|array{method:string, arguments?:Arguments, returns_clone?:bool}
 * @psalm-type Imports = list<string|array{
 *   resource: string,
 *   type?: string|null,
 *   ignore_errors?: bool,
 * }>
 * @psalm-type Parameters = array<string, scalar|\UnitEnum|array<scalar|\UnitEnum|array|null>|null>
 * @psalm-type Defaults = array{
 *   public?: bool,
 *   tags?: Tags,
 *   resource_tags?: Tags,
 *   autowire?: bool,
 *   autoconfigure?: bool,
 *   bind?: array<string, mixed>,
 * }
 * @psalm-type Instanceof = array{
 *   shared?: bool,
 *   lazy?: bool|string,
 *   public?: bool,
 *   properties?: array<string, mixed>,
 *   configurator?: Callback,
 *   calls?: list<Call>,
 *   tags?: Tags,
 *   resource_tags?: Tags,
 *   autowire?: bool,
 *   bind?: array<string, mixed>,
 *   constructor?: string,
 * }
 * @psalm-type Definition = array{
 *   class?: string,
 *   file?: string,
 *   parent?: string,
 *   shared?: bool,
 *   synthetic?: bool,
 *   lazy?: bool|string,
 *   public?: bool,
 *   abstract?: bool,
 *   deprecated?: Deprecation,
 *   factory?: Callback,
 *   configurator?: Callback,
 *   arguments?: Arguments,
 *   properties?: array<string, mixed>,
 *   calls?: list<Call>,
 *   tags?: Tags,
 *   resource_tags?: Tags,
 *   decorates?: string,
 *   decoration_inner_name?: string,
 *   decoration_priority?: int,
 *   decoration_on_invalid?: 'exception'|'ignore'|null|ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE|ContainerInterface::IGNORE_ON_INVALID_REFERENCE|ContainerInterface::NULL_ON_INVALID_REFERENCE,
 *   autowire?: bool,
 *   autoconfigure?: bool,
 *   bind?: array<string, mixed>,
 *   constructor?: string,
 *   from_callable?: mixed,
 * }
 * @psalm-type Alias = string|array{
 *   alias: string,
 *   public?: bool,
 *   deprecated?: Deprecation,
 * }
 * @psalm-type Prototype = array{
 *   resource: string,
 *   namespace?: string,
 *   exclude?: string|list<string>,
 *   parent?: string,
 *   shared?: bool,
 *   lazy?: bool|string,
 *   public?: bool,
 *   abstract?: bool,
 *   deprecated?: Deprecation,
 *   factory?: Callback,
 *   arguments?: Arguments,
 *   properties?: array<string, mixed>,
 *   configurator?: Callback,
 *   calls?: list<Call>,
 *   tags?: Tags,
 *   resource_tags?: Tags,
 *   autowire?: bool,
 *   autoconfigure?: bool,
 *   bind?: array<string, mixed>,
 *   constructor?: string,
 * }
 * @psalm-type Stack = array{
 *   stack: list<Definition|Alias|Prototype|array<class-string, Arguments|null>>,
 *   public?: bool,
 *   deprecated?: Deprecation,
 * }
 * @psalm-type Services = array<string, Definition|Alias|Prototype|Stack>|array<class-string, Arguments|null>
 */
class ServicesConfig
{
    public readonly array $services;

    /**
     * @param Services   $services
     * @param Imports    $imports
     * @param Parameters $parameters
     * @param Defaults   $defaults
     * @param Instanceof $instanceof
     */
    public function __construct(
        array $services = [],
        public readonly array $imports = [],
        public readonly array $parameters = [],
        array $defaults = [],
        array $instanceof = [],
    ) {
        if (isset($services['_defaults']) || isset($services['_instanceof'])) {
            throw new InvalidArgumentException('The $services argument should not contain "_defaults" or "_instanceof" keys, use the $defaults and $instanceof parameters instead.');
        }

        $services['_defaults'] = $defaults;
        $services['_instanceof'] = $instanceof;
        $this->services = $services;
    }
}
