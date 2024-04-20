<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpClient\Chunk;

use Symfony\Component\HttpClient\Exception\JsonException;
use Symfony\Contracts\HttpClient\ChunkInterface;

/**
 * @author Antoine Bluchet <soyuka@gmail.com>
 * @author Nicolas Grekas <p@tchwork.com>
 */
final class ServerSentEvent extends DataChunk implements ChunkInterface
{
    private string $data = '';
    private string $id = '';
    private string $type = 'message';
    private float $retry = 0;
    private ?array $jsonData = null;

    public function __construct(string $content)
    {
        parent::__construct(-1, $content);

        // remove BOM
        if (str_starts_with($content, "\xEF\xBB\xBF")) {
            $content = substr($content, 3);
        }

        foreach (preg_split("/(?:\r\n|[\r\n])/", $content) as $line) {
            if (0 === $i = strpos($line, ':')) {
                continue;
            }

            $i = false === $i ? \strlen($line) : $i;
            $field = substr($line, 0, $i);
            $i += 1 + (' ' === ($line[1 + $i] ?? ''));

            switch ($field) {
                case 'id':
                    $this->id = substr($line, $i);
                    break;
                case 'event':
                    $this->type = substr($line, $i);
                    break;
                case 'data':
                    $this->data .= ('' === $this->data ? '' : "\n").substr($line, $i);
                    break;
                case 'retry':
                    $retry = substr($line, $i);

                    if ('' !== $retry && \strlen($retry) === strspn($retry, '0123456789')) {
                        $this->retry = $retry / 1000.0;
                    }

                    break;
            }
        }
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getData(): string
    {
        return $this->data;
    }

    public function getRetry(): float
    {
        return $this->retry;
    }

    /**
     * Gets the SSE data decoded as an array when it's a JSON payload.
     */
    public function getArrayData(): array
    {
        if (null !== $this->jsonData) {
            return $this->jsonData;
        }

        if ('' === $this->data) {
            throw new JsonException(sprintf('Server-Sent Event%s data is empty.', '' !== $this->id ? sprintf(' "%s"', $this->id) : ''));
        }

        try {
            $jsonData = json_decode($this->data, true, 512, \JSON_BIGINT_AS_STRING | \JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new JsonException(sprintf('Decoding Server-Sent Event%s failed: ', '' !== $this->id ? sprintf(' "%s"', $this->id) : '').$e->getMessage(), $e->getCode());
        }

        if (!\is_array($jsonData)) {
            throw new JsonException(sprintf('JSON content was expected to decode to an array, "%s" returned in Server-Sent Event%s.', get_debug_type($jsonData), '' !== $this->id ? sprintf(' "%s"', $this->id) : ''));
        }

        return $this->jsonData = $jsonData;
    }
}
