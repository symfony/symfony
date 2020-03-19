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

        return 1 === $this->getConfigurationsForArgument($request, $argument)->count();
    }

    public function resolve(Request $request, ArgumentMetadata $argument)
    {
        $configuration = $this->getConfigurationsForArgument($request, $argument)->first();

        // todo validate query param constraints

        yield $request->query->get($configuration->getName());
    }

    private function getConfigurationsForArgument(Request $request, ArgumentMetadata $argument): ConfigurationList
    {
        /** @var ConfigurationList $configurations */
        $configurations = $request->attributes->get('_configurations');

        $configuration = $configurations->filter(function (ConfigurationInterface $configuration) use ($argument): bool {
            return $configuration instanceof QueryParam && $configuration->getArgumentName() === $argument->getName();
        });

        return $configuration;
    }
}
