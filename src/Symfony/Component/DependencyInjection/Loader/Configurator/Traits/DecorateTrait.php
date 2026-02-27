<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Loader\Configurator\Traits;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;

trait DecorateTrait
{
    /**
     * Sets the service that this service is decorating.
     *
     * @param string|null $id The decorated service id, use null to remove decoration
     *
     * @return $this
     *
     * @throws InvalidArgumentException in case the decorated service id and the new decorated service id are equals
     */
    final public function decorate(?string $id, ?string $renamedId = null, int $priority = 0, int $invalidBehavior = ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE): static
    {
        $this->definition->setDecoratedService($id, $renamedId, $priority, $invalidBehavior);

        return $this;
    }

    /**
     * Sets the tag that this definition is decorating.
     *
     * When decorating a tag, this definition acts as a template to create decorator services
     * for each service that has the specified tag.
     *
     * @return $this
     */
    final public function decorateTag(string $tag, int $priority = 0, int $invalidBehavior = ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE): static
    {
        $tagAttributes = [
            'decorates_tag' => $tag,
            'priority' => $priority,
        ];

        if (ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE !== $invalidBehavior) {
            $tagAttributes['on_invalid'] = $invalidBehavior;
        }

        $this->definition->addResourceTag('container.tag_decorator', $tagAttributes);

        return $this;
    }
}
