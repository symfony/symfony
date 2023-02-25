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
use Symfony\Component\HttpKernel\Attribute\MapUploadedFile;
use Symfony\Component\HttpKernel\Controller\ValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @author Konstantin Myakshin <molodchick@gmail.com>
 */
final class UploadedFileValueResolver implements ValueResolverInterface
{
    public function resolve(Request $request, ArgumentMetadata $argument): iterable
    {
        if (!$attribute = $argument->getAttributesOfType(MapUploadedFile::class)[0] ?? null) {
            return [];
        }

        $name = $attribute->name ?? $argument->getName();

        if (!$request->files->has($name)) {
            if ($argument->isNullable() || $argument->hasDefaultValue()) {
                return [];
            }

            if ('array' === $argument->getType()) {
                return [[]];
            }

            throw new NotFoundHttpException(sprintf('Missing uploaded file "%s".', $name));
        }

        $value = $request->files->all()[$name];
        if ('array' === $argument->getType()) {
            $value = (array) $value;
        }

        return $argument->isVariadic() ? $value : [$value];
    }
}
