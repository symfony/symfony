<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\DataCollector;

/**
 * Implement this interface in data collectors that should expose their
 * data as JSON through the web profiler's JSON endpoints.
 *
 * The returned array must contain only JSON-serializable values (no
 * VarDumper\Cloner\Data objects). Collectors that store Data objects
 * internally must resolve them before returning.
 *
 * The array structure is part of Symfony's backward compatibility promise.
 * New keys may be added in minor versions; existing keys cannot be removed
 * or renamed without a deprecation cycle.
 *
 * Note: the array returned by toJsonArray() will have convention-based
 * redaction applied by the profiler controller before being sent as a
 * response. Keys containing "_headers" or equal to "headers" receive
 * header redaction; keys containing "_cookies" or "session" receive full
 * value redaction. Avoid these substrings in non-sensitive field names.
 *
 * @see \Symfony\Bundle\FrameworkBundle\DataCollector\TemplateAwareDataCollectorInterface for the analogous pattern for Twig templates
 */
interface JsonAwareDataCollectorInterface extends DataCollectorInterface
{
    /**
     * Returns the collector's data as a JSON-serializable array.
     *
     * When $verbose is true, collectors may include additional detail
     * (e.g. exception traces, full query logs) that is omitted by default
     * to keep the response compact and reduce information exposure.
     */
    public function toJsonArray(bool $verbose = false): array;
}
