<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\HttpKernel\Controller;

use Symphony\Component\HttpFoundation\Request;
use Symphony\Component\HttpKernel\Controller\ArgumentResolver\DefaultValueResolver;
use Symphony\Component\HttpKernel\Controller\ArgumentResolver\RequestAttributeValueResolver;
use Symphony\Component\HttpKernel\Controller\ArgumentResolver\RequestValueResolver;
use Symphony\Component\HttpKernel\Controller\ArgumentResolver\SessionValueResolver;
use Symphony\Component\HttpKernel\Controller\ArgumentResolver\VariadicValueResolver;
use Symphony\Component\HttpKernel\ControllerMetadata\ArgumentMetadataFactory;
use Symphony\Component\HttpKernel\ControllerMetadata\ArgumentMetadataFactoryInterface;

/**
 * Responsible for resolving the arguments passed to an action.
 *
 * @author Iltar van der Berg <kjarli@gmail.com>
 */
final class ArgumentResolver implements ArgumentResolverInterface
{
    private $argumentMetadataFactory;

    /**
     * @var iterable|ArgumentValueResolverInterface[]
     */
    private $argumentValueResolvers;

    public function __construct(ArgumentMetadataFactoryInterface $argumentMetadataFactory = null, iterable $argumentValueResolvers = array())
    {
        $this->argumentMetadataFactory = $argumentMetadataFactory ?: new ArgumentMetadataFactory();
        $this->argumentValueResolvers = $argumentValueResolvers ?: self::getDefaultArgumentValueResolvers();
    }

    /**
     * {@inheritdoc}
     */
    public function getArguments(Request $request, $controller)
    {
        $arguments = array();

        foreach ($this->argumentMetadataFactory->createArgumentMetadata($controller) as $metadata) {
            foreach ($this->argumentValueResolvers as $resolver) {
                if (!$resolver->supports($request, $metadata)) {
                    continue;
                }

                $resolved = $resolver->resolve($request, $metadata);

                if (!$resolved instanceof \Generator) {
                    throw new \InvalidArgumentException(sprintf('%s::resolve() must yield at least one value.', get_class($resolver)));
                }

                foreach ($resolved as $append) {
                    $arguments[] = $append;
                }

                // continue to the next controller argument
                continue 2;
            }

            $representative = $controller;

            if (is_array($representative)) {
                $representative = sprintf('%s::%s()', get_class($representative[0]), $representative[1]);
            } elseif (is_object($representative)) {
                $representative = get_class($representative);
            }

            throw new \RuntimeException(sprintf('Controller "%s" requires that you provide a value for the "$%s" argument. Either the argument is nullable and no null value has been provided, no default value has been provided or because there is a non optional argument after this one.', $representative, $metadata->getName()));
        }

        return $arguments;
    }

    public static function getDefaultArgumentValueResolvers(): iterable
    {
        return array(
            new RequestAttributeValueResolver(),
            new RequestValueResolver(),
            new SessionValueResolver(),
            new DefaultValueResolver(),
            new VariadicValueResolver(),
        );
    }
}
