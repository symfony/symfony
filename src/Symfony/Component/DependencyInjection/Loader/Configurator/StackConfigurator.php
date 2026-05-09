<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;

/**
 * @author Nicolas Grekas <p@tchwork.com>
 */
class StackConfigurator extends AliasConfigurator
{
    use Traits\DecorateTrait {
        decorate as private doDecorate;
        decorateTag as private doDecorateTag;
    }

    public const FACTORY = 'stack';

    final public function decorate(?string $id, ?string $renamedId = null, int $priority = 0, int $invalidBehavior = ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE): static
    {
        if ($this->definition->hasTag('container.tag_decorator')) {
            throw new InvalidArgumentException('A stack cannot have both "decorate" and "decorateTag".');
        }

        return $this->doDecorate($id, $renamedId, $priority, $invalidBehavior);
    }

    final public function decorateTag(string $tag, int $priority = 0, int $invalidBehavior = ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE): static
    {
        if ($this->definition->getDecoratedService()) {
            throw new InvalidArgumentException('A stack cannot have both "decorate" and "decorateTag".');
        }

        return $this->doDecorateTag($tag, $priority, $invalidBehavior);
    }
}
