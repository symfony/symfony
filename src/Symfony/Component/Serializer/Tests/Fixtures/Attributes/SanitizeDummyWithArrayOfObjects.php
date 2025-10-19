<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Serializer\Tests\Fixtures\Attributes;

/**
 * @author Mohamed Senoussi <lesfootix@gmail.com>
 */
class SanitizeDummyWithArrayOfObjects
{
    public function __construct(
        public string $id,
        /** @var SanitizeDummy[] */
        public array $objects,
    ) {}
}
