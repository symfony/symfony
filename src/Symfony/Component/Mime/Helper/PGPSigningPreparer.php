<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mime\Helper;

use Symfony\Component\Mime\Part\AbstractMultipartPart;
use Symfony\Component\Mime\Part\AbstractPart;

/*
 * @author PuLLi <the@pulli.dev>
 *
 */
trait PGPSigningPreparer
{
    protected function normalizeLineEnding(string $text): string
    {
        return str_replace("\n", "\r\n", str_replace(["\r\n", "\r"], "\n", $text));
    }

    protected function prepareMessageForSigning(AbstractPart $part, string $msg): string
    {
        // Only text part
        if ('text' === $part->getMediaType()) {
            $msg = $this->getMessage($part, $msg);
        } elseif ($part instanceof AbstractMultipartPart) {
            // Find the text part inside the multipart
            $msg = $this->findTextPart($part->getParts(), $msg);
        }

        return $msg;
    }

    /**
     * @param AbstractPart[] $parts
     */
    protected function findTextPart(array $parts, string $msg): string
    {
        foreach ($parts as $part) {
            $msg = $this->prepareMessageForSigning($part, $msg);
        }

        return $msg;
    }

    protected function getMessage(AbstractPart $part, string $msg): string
    {
        $textPart = $part->toString();
        $normalizedText = $this->normalizeLineEnding($textPart);

        // If text part has no extra line endings, add them
        if (str_contains("\r\n--", $textPart)) {
            return str_replace("$textPart\r\n--", "$normalizedText\r\n\r\n--", $msg);
        }

        return str_replace($textPart, "$normalizedText\r\n", $msg);
    }
}
