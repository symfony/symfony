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

use Symfony\Component\Serializer\Attribute\Sanitize;

/**
 * @author Mohamed Senoussi <lesfootix@gmail.com>
 */
class SanitizeDummy
{
    public function __construct(
        public string $id,
        #[Sanitize]
        public string $firstName,
        #[Sanitize]
        public string $lastName,
        #[Sanitize]
        public string $bio
    ) {}
}
