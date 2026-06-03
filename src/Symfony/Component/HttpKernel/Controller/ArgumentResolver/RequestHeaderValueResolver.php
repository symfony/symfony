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

use Symfony\Component\HttpFoundation\AcceptHeader;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\MapRequestHeader;
use Symfony\Component\HttpKernel\Controller\ValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class RequestHeaderValueResolver implements ValueResolverInterface
{
    public function resolve(Request $request, ArgumentMetadata $argument): array
    {
        if (!$attribute = $argument->getAttributesOfType(MapRequestHeader::class)[0] ?? null) {
            return [];
        }

        $type = $argument->getType();

        if (!\in_array($type, ['string', 'array', AcceptHeader::class])) {
            throw new \LogicException(\sprintf('Could not resolve the argument typed "%s". Valid types are "array", "string" or "%s".', $type, AcceptHeader::class));
        }

        $name = $attribute->name ?? strtolower(preg_replace('/[a-z]\K[A-Z]/', '-$0', $argument->getName()));
        $value = null;

        if ($request->headers->has($name)) {
            $value = match ($type) {
                'string' => $request->headers->get($name),
                'array' => match (strtolower($name)) {
                    'accept' => $request->getAcceptableContentTypes(),
                    'accept-charset' => $request->getCharsets(),
                    'accept-language' => $request->getLanguages(),
                    'accept-encoding' => $request->getEncodings(),
                    default => $request->headers->all($name),
                },
                AcceptHeader::class => AcceptHeader::fromString($request->headers->get($name)),
            };
        } elseif ($argument->hasDefaultValue()) {
            $value = $argument->getDefaultValue();
        }

        if (null === $value && 'array' === $type) {
            $value = [];
        }

        if (null === $value && !$argument->isNullable()) {
            throw HttpException::fromStatusCode($attribute->validationFailedStatusCode, \sprintf('Missing header "%s".', $name));
        }

        return [$value];
    }
}
