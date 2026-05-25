<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Tui\Widget;

use Symfony\Component\Tui\Ansi\AnsiUtils;
use Symfony\Component\Tui\Render\RenderContext;
use Symfony\Component\Tui\Widget\Util\StringUtils;

/**
 * Animated progress bar widget.
 *
 * Supports both determinate (with max steps) and indeterminate (no max) modes.
 * Uses a format string with placeholders to render the bar.
 *
 * Built-in placeholders: %current%, %max%, %bar%, %percent%, %elapsed%,
 * %remaining%, %estimated%, %memory%, %message%.
 *
 * The bar animates via the event loop, like the LoaderWidget spinner.
 *
 * @experimental
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class ProgressBarWidget extends AbstractWidget
{
    use ScheduledTickTrait;

    private const TICK_INTERVAL_MS = 100;

    private int $step = 0;
    private int $startingStep = 0;
    private ?int $max;
    private int $stepWidth;
    private float $percent = 0.0;
    private int $startTime;
    private bool $running = false;

    private int $barWidth = 28;
    private string $barChar = '━';
    private string $emptyBarChar = '━';
    private string $progressChar = '';
    private string $format;

    /** @var array<string, string> */
    private array $messages = [];

    /** @var array<string, \Closure(self): string> */
    private array $placeholderFormatters = [];

    /** @var array<string, \Closure(self): string> */
    private static array $defaultPlaceholderFormatters = [];

    public function __construct(
        int $max = 0,
        ?string $format = null,
    ) {
        $this->setMaxSteps($max);
        $this->format = $format ?? ($max > 0 ? self::FORMAT_NORMAL : self::FORMAT_INDETERMINATE);
        $this->startTime = time();
    }

    /**
     * Normal format: ` 3/10 [━━━━━━━━━━━━━━━━━━━━━━━━━━━━]  30%`.
     */
    public const FORMAT_NORMAL = ' %current%/%max% [%bar%] %percent:3s%%';

    /**
     * Indeterminate format (no max): ` 42 [━━━━━━━━━━━━━━━━━━━━━━━━━━━━]`.
     */
    public const FORMAT_INDETERMINATE = ' %current% [%bar%]';

    /**
     * Verbose format: ` 3/10 [━━━━━━━━━━━━━━━━━━━━━━━━━━━━]  30% 0:05`.
     */
    public const FORMAT_VERBOSE = ' %current%/%max% [%bar%] %percent:3s%% %elapsed:6s%';

    /**
     * Very verbose format: ` 3/10 [━━━━━━━━━━━━━━━━━━━━━━━━━━━━]  30% 0:05/0:15`.
     */
    public const FORMAT_VERY_VERBOSE = ' %current%/%max% [%bar%] %percent:3s%% %elapsed:6s%/%estimated:-6s%';

    /**
     * Debug format with memory: ` 3/10 [━━━━━━━━━━━━━━━━━━━━━━━━━━━━]  30% 0:05/0:15 12.0 MiB`.
     */
    public const FORMAT_DEBUG = ' %current%/%max% [%bar%] %percent:3s%% %elapsed:6s%/%estimated:-6s% %memory:6s%';

    /**
     * Verbose indeterminate format: ` 42 [━━━━━━━━━━━━━━━━━━━━━━━━━━━━] 0:05`.
     */
    public const FORMAT_VERBOSE_INDETERMINATE = ' %current% [%bar%] %elapsed:6s%';

    /**
     * Debug indeterminate format: ` 42 [━━━━━━━━━━━━━━━━━━━━━━━━━━━━] 0:05 12.0 MiB`.
     */
    public const FORMAT_DEBUG_INDETERMINATE = ' %current% [%bar%] %elapsed:6s% %memory:6s%';

    /**
     * Start (or restart) the progress bar.
     *
     * @param int|null $max     Maximum steps (0 for indeterminate), null to keep current
     * @param int      $startAt Starting step value
     */
    public function start(?int $max = null, int $startAt = 0): void
    {
        $this->startTime = time();
        $this->step = $startAt;
        $this->startingStep = $startAt;
        $this->percent = 0.0;
        $this->running = true;

        if (null !== $max) {
            $this->setMaxSteps($max);
        }

        if ($startAt > 0) {
            $this->setProgress($startAt);
        }

        $this->invalidate();
        $this->getContext()?->requestRender();
        $this->startScheduledTick(self::TICK_INTERVAL_MS / 1000);
    }

    /**
     * Advance the progress bar by a number of steps.
     */
    public function advance(int $step = 1): void
    {
        $this->setProgress($this->step + $step);
    }

    /**
     * Set the current progress.
     */
    public function setProgress(int $step): void
    {
        if (null !== $this->max && $step > $this->max) {
            $this->max = $step;
            $this->stepWidth = \strlen((string) $this->max);
        } elseif ($step < 0) {
            $step = 0;
        }

        $this->step = $step;

        if (null === $this->max) {
            $this->percent = 0.0;
        } elseif (0 === $this->max) {
            $this->percent = 1.0;
        } else {
            $this->percent = (float) $this->step / $this->max;
        }

        $this->invalidate();
        $this->getContext()?->requestRender();
    }

    /**
     * Finish the progress bar (jump to max).
     */
    public function finish(): void
    {
        if (null === $this->max) {
            $this->max = $this->step;
            $this->stepWidth = \strlen((string) $this->max);
        }

        $this->setProgress($this->max);
        $this->running = false;
        $this->clearScheduledTick();
    }

    /**
     * Check if the progress bar is running.
     */
    public function isRunning(): bool
    {
        return $this->running;
    }

    /**
     * Get the current step.
     */
    public function getProgress(): int
    {
        return $this->step;
    }

    /**
     * Get the maximum number of steps (0 if indeterminate).
     */
    public function getMaxSteps(): int
    {
        return $this->max ?? 0;
    }

    /**
     * Get the progress as a percentage (0.0 to 1.0).
     */
    public function getProgressPercent(): float
    {
        return $this->percent;
    }

    /**
     * Get the start time as a Unix timestamp.
     */
    public function getStartTime(): int
    {
        return $this->startTime;
    }

    /**
     * Get the estimated total time in seconds.
     */
    public function getEstimated(): float
    {
        if (0 === $this->step || $this->step === $this->startingStep) {
            return 0;
        }

        return round((time() - $this->startTime) / ($this->step - $this->startingStep) * ($this->max ?? $this->step));
    }

    /**
     * Get the estimated remaining time in seconds.
     */
    public function getRemaining(): float
    {
        if (null === $this->max || 0 === $this->step || $this->step === $this->startingStep) {
            return 0;
        }

        return round((time() - $this->startTime) / ($this->step - $this->startingStep) * ($this->max - $this->step));
    }

    /**
     * Get the bar offset (number of filled characters).
     */
    public function getBarOffset(): int
    {
        if (null !== $this->max) {
            return (int) floor($this->percent * $this->barWidth);
        }

        return $this->step % $this->barWidth;
    }

    /**
     * Get the width reserved for the step number display.
     */
    public function getStepWidth(): int
    {
        return $this->stepWidth;
    }

    /**
     * @return $this
     */
    public function setFormat(string $format): static
    {
        $this->format = $format;
        $this->invalidate();

        return $this;
    }

    /**
     * Get the current format string.
     */
    public function getFormat(): string
    {
        return $this->format;
    }

    /**
     * @return $this
     */
    public function setBarWidth(int $width): static
    {
        $this->barWidth = max(1, $width);
        $this->invalidate();

        return $this;
    }

    /**
     * Get the bar width in characters.
     */
    public function getBarWidth(): int
    {
        return $this->barWidth;
    }

    /**
     * Set the character used for the filled part of the bar.
     *
     * @return $this
     */
    public function setBarCharacter(string $char): static
    {
        $this->barChar = $char;
        $this->invalidate();

        return $this;
    }

    /**
     * Get the character used for the filled part of the bar.
     */
    public function getBarCharacter(): string
    {
        return $this->barChar;
    }

    /**
     * Set the character used for the empty part of the bar.
     *
     * @return $this
     */
    public function setEmptyBarCharacter(string $char): static
    {
        $this->emptyBarChar = $char;
        $this->invalidate();

        return $this;
    }

    /**
     * Get the character used for the empty part of the bar.
     */
    public function getEmptyBarCharacter(): string
    {
        return $this->emptyBarChar;
    }

    /**
     * Set the character displayed at the progress position.
     *
     * @return $this
     */
    public function setProgressCharacter(string $char): static
    {
        $this->progressChar = $char;
        $this->invalidate();

        return $this;
    }

    /**
     * Get the character displayed at the progress position.
     */
    public function getProgressCharacter(): string
    {
        return $this->progressChar;
    }

    /**
     * Set the maximum number of steps.
     */
    public function setMaxSteps(int $max): void
    {
        if (0 === $max) {
            $this->max = null;
            $this->stepWidth = 4;
        } else {
            $this->max = max(0, $max);
            $this->stepWidth = \strlen((string) $this->max);
        }

        $this->invalidate();
    }

    /**
     * Associate a named message for use in the format string via %message_name%.
     *
     * @return $this
     */
    public function setMessage(string $message, string $name = 'message'): static
    {
        $this->messages[$name] = StringUtils::stripControlBytes(StringUtils::sanitizeUtf8($message));
        $this->invalidate();
        $this->getContext()?->requestRender();

        return $this;
    }

    /**
     * Get a named message.
     */
    public function getMessage(string $name = 'message'): ?string
    {
        return $this->messages[$name] ?? null;
    }

    /**
     * Set a placeholder formatter for this instance.
     *
     * @param \Closure(self): string $formatter
     *
     * @return $this
     */
    public function setPlaceholderFormatter(string $name, \Closure $formatter): static
    {
        $this->placeholderFormatters[$name] = $formatter;
        $this->invalidate();

        return $this;
    }

    /**
     * Get a placeholder formatter (instance-level, then global).
     *
     * @return (\Closure(self): string)|null
     */
    public function getPlaceholderFormatter(string $name): ?\Closure
    {
        return $this->placeholderFormatters[$name] ?? self::$defaultPlaceholderFormatters[$name] ?? null;
    }

    /**
     * Set a global placeholder formatter for all ProgressBarWidget instances.
     *
     * @param \Closure(self): string $formatter
     */
    public static function setDefaultPlaceholderFormatter(string $name, \Closure $formatter): void
    {
        self::$defaultPlaceholderFormatters[$name] = $formatter;
    }

    /**
     * Tick the animation.
     *
     * In indeterminate mode (no max), advances the bar offset by one
     * cell each tick so the bar visibly bounces. In determinate mode
     * the offset tracks {@see setProgress()} and tick() only invalidates
     * the render cache so dependent placeholders ({@see %elapsed%},
     * {@see %memory%}, etc.) can refresh.
     */
    public function tick(): bool
    {
        if (!$this->running) {
            return false;
        }

        if (null === $this->max) {
            ++$this->step;
        }

        $this->invalidate();

        return true;
    }

    /**
     * @return string[]
     */
    public function render(RenderContext $context): array
    {
        $columns = $context->getColumns();
        $line = $this->buildLine($columns);

        $styledLine = $this->applyElement('text', $line);
        $visibleLen = AnsiUtils::visibleWidth($styledLine);
        $rightFill = str_repeat(' ', max(0, $columns - $visibleLen));

        return [$styledLine.$rightFill];
    }

    protected function onAttach(WidgetContext $context): void
    {
        if ($this->running) {
            $this->resumeScheduledTick();
        }
    }

    protected function onDetach(): void
    {
        $this->stopScheduledTick();
    }

    protected function onScheduledTick(): void
    {
        $this->tick();
    }

    protected function resolveScheduledTickContext(): ?WidgetContext
    {
        return $this->getContext();
    }

    private function buildLine(int $availableWidth): string
    {
        $format = $this->format;

        // First pass: resolve all placeholders to measure total width
        $line = $this->replacePlaceholders($format);

        // If the line is too wide, shrink the bar to fit
        $lineWidth = AnsiUtils::visibleWidth($line);
        if ($lineWidth > $availableWidth) {
            $newBarWidth = $this->barWidth - ($lineWidth - $availableWidth);
            if ($newBarWidth >= 1) {
                $savedBarWidth = $this->barWidth;
                $this->barWidth = $newBarWidth;
                $line = $this->replacePlaceholders($format);
                $this->barWidth = $savedBarWidth;
            }
        }

        return $line;
    }

    private function replacePlaceholders(string $format): string
    {
        return preg_replace_callback('{%([a-z\-_]+)(?::([^%]+))?%}i', function (array $matches): string {
            $name = $matches[1];

            $text = match ($name) {
                'bar' => $this->renderBar(),
                'elapsed' => self::formatTime(time() - $this->startTime),
                'remaining' => self::formatTime((int) $this->getRemaining()),
                'estimated' => self::formatTime((int) $this->getEstimated()),
                'memory' => self::formatMemory(memory_get_usage(true)),
                'current' => str_pad((string) $this->step, $this->stepWidth, ' ', \STR_PAD_LEFT),
                'max' => (string) ($this->max ?? 0),
                'percent' => (string) (int) floor($this->percent * 100),
                default => null,
            };

            if (null === $text) {
                $formatter = $this->getPlaceholderFormatter($name);
                if (null !== $formatter) {
                    $text = $formatter($this);
                } elseif (isset($this->messages[$name])) {
                    $text = $this->messages[$name];
                } else {
                    return $matches[0];
                }
            }

            if (isset($matches[2])) {
                $text = \sprintf('%'.$matches[2], $text);
            }

            return $text;
        }, $format) ?? $format;
    }

    private function renderBar(): string
    {
        $completeBars = $this->getBarOffset();
        $styledComplete = $this->applyElement('bar-fill', str_repeat($this->barChar, $completeBars));

        if ($completeBars < $this->barWidth) {
            $progressCharLen = mb_strlen($this->progressChar);
            $emptyBars = $this->barWidth - $completeBars - $progressCharLen;
            $styledProgress = '' !== $this->progressChar ? $this->applyElement('bar-progress', $this->progressChar) : '';
            $styledEmpty = $this->applyElement('bar-empty', str_repeat($this->emptyBarChar, max(0, $emptyBars)));

            return $styledComplete.$styledProgress.$styledEmpty;
        }

        return $styledComplete;
    }

    private static function formatTime(int $secs): string
    {
        if ($secs < 0) {
            $secs = 0;
        }

        $hours = (int) ($secs / 3600);
        $minutes = (int) (($secs % 3600) / 60);
        $seconds = $secs % 60;

        if ($hours > 0) {
            return \sprintf('%d:%02d:%02d', $hours, $minutes, $seconds);
        }

        return \sprintf('%d:%02d', $minutes, $seconds);
    }

    private static function formatMemory(int $memory): string
    {
        if ($memory >= 1024 * 1024 * 1024) {
            return \sprintf('%.1f GiB', $memory / 1024 / 1024 / 1024);
        }

        if ($memory >= 1024 * 1024) {
            return \sprintf('%.1f MiB', $memory / 1024 / 1024);
        }

        if ($memory >= 1024) {
            return \sprintf('%d KiB', $memory / 1024);
        }

        return \sprintf('%d B', $memory);
    }
}
