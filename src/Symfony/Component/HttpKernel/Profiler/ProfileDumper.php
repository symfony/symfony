<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Profiler;

use Symfony\Component\ErrorHandler\Exception\FlattenException;
use Symfony\Component\HttpKernel\DataCollector\DataCollector;
use Symfony\Component\VarDumper\Cloner\Data;

/**
 * Dumps the data collected in a Profile as Markdown or as an array.
 *
 * The output is meant to be consumed from the terminal, e.g. by AI agents
 * inspecting the last requests made to the application. Long values are
 * truncated by default, so the dump is a summary of the profile rather than
 * a faithful export; pass \PHP_INT_MAX as the limits to disable truncation.
 *
 * @author Javier Eguiluz <javier.eguiluz@gmail.com>
 */
final class ProfileDumper
{
    private const MAX_INLINE_JSON_LENGTH = 120;

    public function __construct(
        private int $maxStringLength = 2048,
        private int $maxDepth = 4,
        private int $maxItemsPerLevel = 50,
        private int $maxTraceFrames = 10,
    ) {
    }

    /**
     * @param string[]|null $panels The names of the panels to dump, or null to dump all non-empty panels
     *
     * @throws \InvalidArgumentException When one of the requested panels doesn't exist in the profile
     */
    public function toArray(Profile $profile, ?array $panels = null): array
    {
        $time = $profile->getTime() ? \DateTimeImmutable::createFromFormat('U', (string) $profile->getTime())->format(\DateTimeInterface::RFC3339) : null;

        return [
            'token' => $profile->getToken(),
            'type' => $profile->getVirtualType() ?? 'request',
            'method' => $profile->getMethod(),
            'url' => $profile->getUrl(),
            'status_code' => $profile->getStatusCode(),
            'time' => $time,
            'has_errors' => $profile->hasErrors(),
            'collectors' => $this->extract($profile, $panels),
        ];
    }

    /**
     * @param string[]|null $panels The names of the panels to dump, or null to dump all non-empty panels
     *
     * @throws \InvalidArgumentException When one of the requested panels doesn't exist in the profile
     */
    public function toMarkdown(Profile $profile, ?array $panels = null): string
    {
        $data = $this->toArray($profile, $panels);

        $title = \sprintf('# Profile %s', $data['token']);
        if (null !== $data['method'] || null !== $data['url']) {
            $title .= \sprintf(' — %s', trim($data['method'].' '.$data['url']));
        }
        if (null !== $data['status_code']) {
            $title .= \sprintf(' → %d', $data['status_code']);
        }

        $output = $title."\n";
        $output .= \sprintf("- time: %s | type: %s | errors: %s\n", $data['time'] ?? 'n/a', $data['type'], $data['has_errors'] ? 'yes' : 'no');

        foreach ($data['collectors'] as $name => $collectorData) {
            $output .= "\n".$this->renderPanel($name, $collectorData);
        }

        return $output;
    }

    /**
     * @param string[]|null $panels
     */
    private function extract(Profile $profile, ?array $panels): array
    {
        $collectors = $profile->getCollectors();

        if (null !== $panels && $unknownPanels = array_diff($panels, array_keys($collectors))) {
            throw new \InvalidArgumentException(\sprintf('The following panels are not available in this profile: "%s". Available panels: "%s".', implode('", "', $unknownPanels), implode('", "', array_keys($collectors))));
        }

        $data = [];
        foreach ($collectors as $name => $collector) {
            if (null !== $panels && !\in_array($name, $panels, true)) {
                continue;
            }

            try {
                if (!$collector instanceof DataCollector) {
                    $collectorData = null !== $panels ? \sprintf('The "%s" collector does not expose its data.', get_debug_type($collector)) : null;
                } else {
                    $collectorData = $this->normalize($collector->__serialize()['data'] ?? []);
                }
            } catch (\Throwable $e) {
                $collectorData = ['error' => \sprintf('Unable to dump this panel: %s', $e->getMessage())];
            }

            // omit empty panels, except when explicitly requested
            if (null === $panels && (null === $collectorData || [] === $collectorData)) {
                continue;
            }

            $data[$name] = $collectorData;
        }

        // the exception panel is the most valuable one when debugging, show it first
        if (isset($data['exception'])) {
            $data = ['exception' => $data['exception']] + $data;
        }

        return $data;
    }

    private function normalize(mixed $value, int $depth = 0): mixed
    {
        if ($value instanceof Data) {
            $value = $value->getValue(true);
        }

        if (\is_array($value)) {
            if ($depth >= $this->maxDepth) {
                return '…';
            }

            $normalized = [];
            $i = 0;
            foreach ($value as $k => $v) {
                if (++$i > $this->maxItemsPerLevel) {
                    $normalized[] = \sprintf('… %d more items', \count($value) - $this->maxItemsPerLevel);
                    break;
                }
                // objects cast to arrays mangle the property names ("\0ClassName\0name" for
                // private, "\0*\0name" for protected, etc.); replace that by the visibility
                // notation used by VarDumper ("-name" for private, "#name" for protected,
                // "+name" for dynamic and the bare name for virtual properties)
                if (\is_string($k) && str_starts_with($k, "\0") && false !== $pos = strrpos($k, "\0")) {
                    $prefix = substr($k, 1, $pos - 1);
                    $k = match (true) {
                        '*' === $prefix => '#',
                        '+' === $prefix => '+',
                        str_starts_with($prefix, '~') => '',
                        default => '-',
                    }.substr($k, 1 + $pos);
                }
                $normalized[$k] = $this->normalize($v, 1 + $depth);
            }

            return $normalized;
        }

        if (\is_string($value)) {
            return \strlen($value) > $this->maxStringLength ? substr($value, 0, $this->maxStringLength).'… (truncated)' : $value;
        }

        if ($value instanceof FlattenException) {
            return $this->normalizeException($value, $depth);
        }

        if ($value instanceof \UnitEnum) {
            return $value instanceof \BackedEnum ? $value->value : $value->name;
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format(\DateTimeInterface::RFC3339);
        }

        if ($value instanceof \Stringable) {
            return $this->normalize((string) $value, $depth);
        }

        if (\is_object($value)) {
            return \sprintf('(object) %s', get_debug_type($value));
        }

        return $value;
    }

    private function normalizeException(FlattenException $exception, int $depth): array
    {
        $data = [
            'class' => $exception->getClass(),
            'message' => $this->normalize($exception->getMessage(), $depth),
            'at' => $exception->getFile().':'.$exception->getLine(),
        ];

        $trace = [];
        foreach ($exception->getTrace() as $i => $frame) {
            if ($i >= $this->maxTraceFrames) {
                $trace[] = \sprintf('… %d more frames', \count($exception->getTrace()) - $this->maxTraceFrames);
                break;
            }
            $trace[] = \sprintf('%s%s%s() (%s:%d)', $frame['class'], $frame['type'], $frame['function'], $frame['file'], $frame['line']);
        }
        $data['trace'] = $trace;

        if ($previous = $exception->getPrevious()) {
            $data['previous'] = $this->normalizeException($previous, $depth);
        }

        return $data;
    }

    private function renderPanel(string $name, mixed $data): string
    {
        $output = \sprintf("## %s\n", $name);

        if (!\is_array($data)) {
            return $output.\sprintf("- %s\n", $this->stringify($data));
        }

        foreach ($data as $key => $value) {
            // skip empty values to make the output more compact
            if (null === $value || '' === $value || [] === $value) {
                continue;
            }

            if (\is_scalar($value)) {
                $output .= \sprintf("- %s: %s\n", $key, $this->stringify($value));
                continue;
            }

            $json = json_encode($value, \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE | \JSON_INVALID_UTF8_SUBSTITUTE);
            if (\strlen($json) <= self::MAX_INLINE_JSON_LENGTH) {
                $output .= \sprintf("- %s: %s\n", $key, $json);
            } else {
                $output .= \sprintf("- %s:\n  ```json\n  %s\n  ```\n", $key, $json);
            }
        }

        return $output;
    }

    private function stringify(string|int|float|bool|null $value): string
    {
        return match (true) {
            null === $value => 'null',
            \is_bool($value) => $value ? 'true' : 'false',
            default => (string) $value,
        };
    }
}
