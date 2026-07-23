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

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Defines a listener for a controller attribute event.
 *
 * @author Grégoire Pineau <lyrixx@lyrixx.info>
 */
#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
final class AsControllerAttributeListener extends AsEventListener
{
    /**
     * @param string       $kernelEvent    The kernel event to listen to (e.g. "Symfony\Component\HttpKernel\Event\ControllerArgumentsEvent::class")
     * @param class-string $attributeClass The attribute class to listen to (e.g. "App\Controller\MyAttribute")
     * @param string|null  $method         The method to run when the listened event is triggered
     * @param int          $priority       The priority of this listener if several are declared for the same event and attribute pair
     */
    public function __construct(
        string $kernelEvent,
        string $attributeClass,
        ?string $method = null,
        int $priority = 0,
    ) {
        $kernelEvent = KernelEvents::ALIASES[$kernelEvent] ?? $kernelEvent;

        parent::__construct($kernelEvent.'.'.$attributeClass, $method, $priority);
    }
}
