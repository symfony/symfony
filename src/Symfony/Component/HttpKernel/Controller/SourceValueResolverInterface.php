<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Controller;

/**
 * Marks a value resolver that selects where a value comes from, leaving its type to another resolver.
 *
 * Such a resolver reads a raw value from the request (a query parameter, a header, the body) and, when it
 * cannot build the argument type itself, stages that value in the request attributes and abstains. The
 * resolvers registered after it then see the value exactly as if it had come from the route, so the
 * existing type-aware resolvers apply without knowing which bag the value came from.
 *
 * Pinning such a resolver with a #[ValueResolver] attribute keeps the whole resolver chain behind it,
 * instead of narrowing it to the request-attribute and default resolvers.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
interface SourceValueResolverInterface
{
}
