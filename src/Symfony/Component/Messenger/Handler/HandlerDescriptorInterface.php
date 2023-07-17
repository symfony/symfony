<?php

declare(strict_types=1);

namespace Symfony\Component\Messenger\Handler;

/**
 * Describes a handler and the possible associated options, such as `from_transport`, `bus`, etc.
 *
 * @author Samuel Roze <samuel.roze@gmail.com>
 * @author Ruud Kamphuis <ruud@ticketswap.com>
 */
interface HandlerDescriptorInterface
{
    public function getHandler() : callable;

    public function getName() : string;

    public function getBatchHandler() : ?BatchHandlerInterface;

    public function getOption(string $option) : mixed;
}
