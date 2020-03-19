<?php

namespace Symfony\Component\HttpKernel\Controller\ArgumentResolver;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ArgumentValueResolverInterface;
use Symfony\Component\HttpKernel\Controller\Configuration\ConfigurationInterface;
use Symfony\Component\HttpKernel\Controller\Configuration\QueryParam;
use Symfony\Component\HttpKernel\Controller\ConfigurationList;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;

final class QueryParamValueResolver implements ArgumentValueResolverInterface
{
    public function supports(Request $request, ArgumentMetadata $argument)
    {
        if (!$request->attributes->has('_configurations')) {
            return false;
        }

        /** @var QueryParam $configuration */
        $configuration = $this->getConfigurationForArgument($request, $argument);

        return null !== $configuration
            && !$argument->isVariadic()
            && ($request->query->has($configuration->getName()) || $argument->hasDefaultValue() || $argument->isNullable());
    }

    public function resolve(Request $request, ArgumentMetadata $argument)
    {
        $configuration = $this->getConfigurationForArgument($request, $argument);
        $defaultValue = $argument->hasDefaultValue() ? $argument->getDefaultValue() : null;
        yield $request->query->get($configuration->getName(), $defaultValue);
    }

    private function getConfigurationForArgument(Request $request, ArgumentMetadata $argument): ?ConfigurationInterface
    {
        /** @var ConfigurationList $configurations */
        $configurations = $request->attributes->get('_configurations');

        $configuration = $configurations->filter(function (ConfigurationInterface $configuration) use ($argument): bool {
            return $configuration instanceof QueryParam && $configuration->getArgumentName() === $argument->getName();
        });

        return $configuration->first();
    }
}
