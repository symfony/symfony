<?php

namespace Symfony\Component\Debug\Tests\Fixtures\DiscourageSerializable;

/**
 * @method array __serialize(): array
 * @method void __unserialize(array $data): void
 */
interface ExtendsSerializableWithTheNewMechanismThroughVirtualMethods extends \Serializable
{
}
