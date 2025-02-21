<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Attribute;

use Symfony\Component\ArgumentResolver\ValueResolver\ValueResolverInterface;
use Symfony\Component\HttpKernel\Controller\ArgumentResolver\ControllerValueResolverInterface;
use Symfony\Component\HttpKernel\Controller\ArgumentResolver\DateTimeValueResolver;
use Symfony\Component\ArgumentResolver\Attribute\MapDateTime as BaseMapDateTime;
use Symfony\Component\HttpKernel\Controller\ValueResolverInterface as LegacyValueResolverInterface;

/**
 * Controller parameter tag to configure DateTime arguments.
 *
 * @deprecated since Symfony 7.3, use {@see BaseMapDateTime} instead
 */
#[\Attribute(\Attribute::TARGET_PARAMETER)]
class MapDateTime extends BaseMapDateTime
{
    /**
     * @param string|null $format   The DateTime format to use, @see https://php.net/datetime.format
     * @param bool $disabled Whether this value resolver is disabled; this allows to enable a value resolver globally while disabling it in specific cases
     * @param class-string<ControllerValueResolverInterface>|string $resolver The name of the resolver to use
     */
    public function __construct(
        public ?string $format = null,
        bool $disabled = false,
        string $resolver = DateTimeValueResolver::class,
    ) {
        parent::__construct($format, $disabled, $resolver);
    }
}
