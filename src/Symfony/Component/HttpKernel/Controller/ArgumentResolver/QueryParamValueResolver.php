<?php

namespace Symfony\Component\HttpKernel\Controller\ArgumentResolver;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ArgumentValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerConfiguration\Configuration\QueryParam;
use Symfony\Component\HttpKernel\ControllerConfiguration\ConfigurationInterface;
use Symfony\Component\HttpKernel\ControllerConfiguration\ConfigurationList;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;

final class QueryParamValueResolver implements ArgumentValueResolverInterface
{
    public function supports(Request $request, ArgumentMetadata $argument)
    {
        if (!$request->attributes->has('_configurations')) {
            return false;
        }

        /** @var QueryParam $configuration */
        $configuration = $this->getConfigurationsForArgument($request, $argument);

        return null !== $configuration
            && !$argument->isVariadic()
            && ($request->query->has($configuration->getName()) || $argument->hasDefaultValue());
    }

    public function resolve(Request $request, ArgumentMetadata $argument)
    {
        $configuration = $this->getConfigurationsForArgument($request, $argument);
        $defaultValue = $argument->hasDefaultValue() ? $argument->getDefaultValue() : null;
        yield $request->query->get($configuration->getName(), $defaultValue);
    }

    private function getConfigurationsForArgument(Request $request, ArgumentMetadata $argument): ?ConfigurationInterface
    {
        /** @var ConfigurationList $configurations */
        $configurations = $request->attributes->get('_configurations');

        $configuration = $configurations->filter(function (ConfigurationInterface $configuration) use ($argument): bool {
            return $configuration instanceof QueryParam && $configuration->getArgumentName() === $argument->getName();
        });

        return $configuration->first();
    }
}
