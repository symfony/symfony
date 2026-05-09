<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests\Functional\Bundle\TestBundle\MimeType;

use Symfony\Component\Mime\MimeTypeGuesserInterface;

class CustomMimeTypeGuesser implements MimeTypeGuesserInterface
{
    public const FAKE_MIME_TYPE = 'application/x-test-custom-guesser';

    public function isGuesserSupported(): bool
    {
        return true;
    }

    public function guessMimeType(string $path): ?string
    {
        return self::FAKE_MIME_TYPE;
    }
}
