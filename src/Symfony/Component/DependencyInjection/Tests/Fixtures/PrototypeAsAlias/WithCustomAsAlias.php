<?php

namespace Symfony\Component\DependencyInjection\Tests\Fixtures\PrototypeAsAlias;

use Symfony\Component\DependencyInjection\Attribute\AsAlias;

#[AsProductionAlias(id: AliasFooInterface::class)]
class WithCustomAsAlias
{
}

#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::IS_REPEATABLE)]
final class AsProductionAlias extends AsAlias
{
    /**
     * @param string|null         $id     The id of the alias
     * @param bool                $public Whether to declare the alias public
     */
    public function __construct(
        public ?string $id = null,
        public bool $public = false,
    ) {
        parent::__construct($id, $public, ['prod']);
    }
}
