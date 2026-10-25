<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mime\Part\Multipart;

use Symfony\Component\Mime\Part\AbstractMultipartPart;
use Symfony\Component\Mime\Part\AbstractPart;

/**
 * @author Fabien Potencier <fabien@symfony.com>
 */
final class RelatedPart extends AbstractMultipartPart
{
    public function __construct(
        private AbstractPart $mainPart,
        AbstractPart $part,
        AbstractPart ...$parts,
    ) {
        $this->prepareParts($part, ...$parts);

        parent::__construct($part, ...$parts);
    }

    public function getParts(): array
    {
        return array_merge([$this->mainPart], parent::getParts());
    }

    public function getMediaSubtype(): string
    {
        return 'related';
    }

    public function __serialize(): array
    {
        return parent::__serialize() + ['mainPart' => $this->mainPart];
    }

    public function __unserialize(array $data): void
    {
        foreach (['mainPart', "\0".self::class."\0mainPart"] as $key) {
            if (\array_key_exists($key, $data)) {
                $this->mainPart = $data[$key];
                unset($data[$key]);
                break;
            }
        }

        parent::__unserialize($data);
    }

    private function generateContentId(): string
    {
        return bin2hex(random_bytes(16)).'@symfony';
    }

    private function prepareParts(AbstractPart ...$parts): void
    {
        foreach ($parts as $part) {
            if (!$part->getHeaders()->has('Content-ID')) {
                $part->getHeaders()->setHeaderBody('Id', 'Content-ID', $this->generateContentId());
            }
        }
    }
}
