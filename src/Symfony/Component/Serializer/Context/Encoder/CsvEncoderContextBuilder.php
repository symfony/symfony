<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Serializer\Context\Encoder;

use Symfony\Component\Serializer\Context\ContextBuilderInterface;
use Symfony\Component\Serializer\Context\ContextBuilderTrait;
use Symfony\Component\Serializer\Encoder\CsvEncoder;
use Symfony\Component\Serializer\Exception\InvalidArgumentException;

/**
 * A helper providing autocompletion for available CsvEncoder options.
 *
 * @author Mathias Arlaud <mathias.arlaud@gmail.com>
 */
final class CsvEncoderContextBuilder implements ContextBuilderInterface
{
    use ContextBuilderTrait;

    /**
     * Configures the column delimiter character.
     *
     * Must be a single character.
     *
     * @throws InvalidArgumentException
     */
    public function withDelimiter(?string $delimiter): static
    {
        if (null !== $delimiter && 1 !== \strlen($delimiter)) {
            throw new InvalidArgumentException(sprintf('The "%s" delimiter must be a single character.', $delimiter));
        }

        return $this->with(CsvEncoder::DELIMITER_KEY, $delimiter);
    }

    /**
     * Configures the field enclosure character.
     *
     * Must be a single character.
     *
     * @throws InvalidArgumentException
     */
    public function withEnclosure(?string $enclosure): static
    {
        if (null !== $enclosure && 1 !== \strlen($enclosure)) {
            throw new InvalidArgumentException(sprintf('The "%s" enclosure must be a single character.', $enclosure));
        }

        return $this->with(CsvEncoder::ENCLOSURE_KEY, $enclosure);
    }

    /**
     * Configures the escape character.
     *
     * Must be empty or a single character.
     *
     * @throws InvalidArgumentException
     */
    public function withEscapeChar(?string $escapeChar): static
    {
        if (null !== $escapeChar && \strlen($escapeChar) > 1) {
            throw new InvalidArgumentException(sprintf('The "%s" escape character must be empty or a single character.', $escapeChar));
        }

        return $this->with(CsvEncoder::ESCAPE_CHAR_KEY, $escapeChar);
    }

    /**
     * Configures the key separator when (un)flattening arrays.
     */
    public function withKeySeparator(?string $keySeparator): static
    {
        return $this->with(CsvEncoder::KEY_SEPARATOR_KEY, $keySeparator);
    }

    /**
     * Configures the headers.
     *
     * @param list<mixed>|null $headers
     */
    public function withHeaders(?array $headers): static
    {
        return $this->with(CsvEncoder::HEADERS_KEY, $headers);
    }

    /**
     * Configures whether formulas should be escaped.
     */
    public function withEscapedFormulas(?bool $escapedFormulas): static
    {
        return $this->with(CsvEncoder::ESCAPE_FORMULAS_KEY, $escapedFormulas);
    }

    /**
     * Configures whether the decoded result should be considered as a collection
     * or as a single element.
     */
    public function withAsCollection(?bool $asCollection): static
    {
        return $this->with(CsvEncoder::AS_COLLECTION_KEY, $asCollection);
    }

    /**
     * Configures whether the input (or output) is containing (or will contain) headers.
     */
    public function withNoHeaders(?bool $noHeaders): static
    {
        return $this->with(CsvEncoder::NO_HEADERS_KEY, $noHeaders);
    }

    /**
     * Configures the end of line characters.
     */
    public function withEndOfLine(?string $endOfLine): static
    {
        return $this->with(CsvEncoder::END_OF_LINE, $endOfLine);
    }

    /**
     * Configures whether to add the UTF-8 Byte Order Mark (BOM)
     * at the beginning of the encoded result or not.
     */
    public function withOutputUtf8Bom(?bool $outputUtf8Bom): static
    {
        return $this->with(CsvEncoder::OUTPUT_UTF8_BOM_KEY, $outputUtf8Bom);
    }
}
