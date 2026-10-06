<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\JsonSchema\Tests\Fixtures;

use Symfony\Component\Serializer\Attribute\Context;

class DatedEvent
{
    #[Context(['datetime_format' => 'Y-m-d'])]
    public \DateTimeImmutable $day;

    #[Context(normalizationContext: ['datetime_format' => 'Y-m-d'], denormalizationContext: ['datetime_format' => 'd/m/Y'])]
    public \DateTimeImmutable $deadline;

    public \DateTimeImmutable $createdAt;
}
