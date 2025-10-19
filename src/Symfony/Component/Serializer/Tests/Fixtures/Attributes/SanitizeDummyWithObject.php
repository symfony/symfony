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
class SanitizeDummyWithObject
{
    public function __construct(
        public string        $id,
        public SanitizeDummy $object,
    ) {}
}
