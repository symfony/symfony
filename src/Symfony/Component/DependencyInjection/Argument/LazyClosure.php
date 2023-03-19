<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Argument;

use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\Exception\RuntimeException;
use Symfony\Component\VarExporter\ProxyHelper;

/**
 * @author Nicolas Grekas <p@tchwork.com>
 *
 * @internal
 */
class LazyClosure
{
    public readonly object $service;

    public function __construct(
        private \Closure $initializer,
    ) {
        unset($this->service);
    }

    public function __get(mixed $name): mixed
    {
        if ('service' !== $name) {
            throw new InvalidArgumentException(sprintf('Cannot read property "%s" from a lazy closure.', $name));
        }

        if (isset($this->initializer)) {
            $this->service = ($this->initializer)();
            unset($this->initializer);
        }

        return $this->service;
    }

    public static function getCode(string $initializer, ?\ReflectionClass $r, string $method, ?string $id): string
    {
        if (!$r || !$r->hasMethod($method)) {
            throw new RuntimeException(sprintf('Cannot create lazy closure for service "%s" because its corresponding callable is invalid.', $id));
        }

        $signature = ProxyHelper::exportSignature($r->getMethod($method));
        $signature = preg_replace('/: static$/', ': \\'.$r->name, $signature);

        return '(new class('.$initializer.') extends \\'.self::class.' { '
            .$signature.' { return $this->service->'.$method.'(...\func_get_args()); } '
            .'})->'.$method.'(...)';
    }
}
