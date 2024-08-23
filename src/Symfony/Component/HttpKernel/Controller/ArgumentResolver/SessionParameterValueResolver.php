<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Controller\ArgumentResolver;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\MapSessionParameter;
use Symfony\Component\HttpKernel\Controller\ValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;

final class SessionParameterValueResolver implements ValueResolverInterface
{
    public function resolve(Request $request, ArgumentMetadata $argument): array
    {
        if (!$attribute = $argument->getAttributesOfType(MapSessionParameter::class, ArgumentMetadata::IS_INSTANCEOF)[0] ?? null) {
            return [];
        }

        if (!$request->hasSession()) {
            return [];
        }

        if ((!$type = $argument->getType()) || (!class_exists($type) && !interface_exists($type, false))) {
            if ($type && (str_contains($type, '|') || str_contains($type, '&'))) {
                if (!$argument->hasDefaultValue() && !$argument->isNullable()) {
                    throw new \LogicException(\sprintf('#[MapSessionParameter] cannot be used on controller argument "$%s": "%s" is an union or intersection type, you need to make the parameter nullable or provide a default value..', $argument->getName(), $type));
                }
            } else {
                throw new \LogicException(\sprintf('#[MapSessionParameter] cannot be used on controller argument "$%s": "%s" is not a class or interface name.', $argument->getName(), $type));
            }
        }

        if (interface_exists($type, false) && !$argument->hasDefaultValue() && !$argument->isNullable()) {
            throw new \LogicException(\sprintf('#[MapSessionParameter] cannot be used on controller argument "$%s": "%s" is an interface, you need to make the parameter nullable or provide a default value.', $argument->getName(), $type));
        }

        $name = $attribute->name ?? $argument->getName();
        if ($request->getSession()->has($name)) {
            $value = $request->getSession()->get($name);
            if (!$value instanceof $type && !str_contains($type, '|') && !str_contains($type, '&')) {
                throw new \LogicException(\sprintf('#[MapSessionParameter] cannot be used to map controller argument "$%s": the session contains a value of type "%s" which is not an instance of "%s".', $argument->getName(), get_debug_type($value), $type));
            }

            return [$value];
        }

        if ($argument->hasDefaultValue()) {
            $value = $argument->getDefaultValue();
        } else {
            // handle the type SessionInterface|null $param, which doesn't have a default value and can't be instantiated.
            $value = class_exists($type, false) ? new $type() : null;
        }

        if (\is_object($value)) {
            $request->getSession()->set($name, $value);
        }

        return [$value];
    }
}
