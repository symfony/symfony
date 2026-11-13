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
use Symfony\Component\Tui\Exception\InvalidArgumentException;
use Symfony\Component\Tui\Loop\PeriodicStepper;
use Symfony\Component\Tui\Render\RenderContext;
use Symfony\Component\Tui\Widget\Util\StringUtils;

/**
 * Animated loading spinner.
 *
 * Newly constructed loaders are stopped; call {@see start()} to begin
 * the animation. Attaching a stopped loader to the widget tree does not
 * schedule any ticks until start() is called.
 *
 * @experimental
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class LoaderWidget extends AbstractWidget
{
    use ScheduledTickTrait;

    private const string DEFAULT_STYLE = 'dots';
    private const DEFAULT_INTERVAL_MS = 80;

    /** @var array<string, string[]> */
    private static array $styles = [
        'line' => ['-', '\\', '|', '/'],
        'dots' => ['⠋', '⠙', '⠹', '⠸', '⠼', '⠴', '⠦', '⠧', '⠇', '⠏'],
        'bounce' => ['⠁', '⠂', '⠄', '⡀', '⢀', '⠠', '⠐', '⠈'],
        'pulse' => ['⠁', '⠉', '⠋', '⠛', '⠟', '⠿', '⡿', '⣿', '⡿', '⠿', '⠟', '⠛', '⠋', '⠉', '⠁'],
        'bar' => ['▁', '▂', '▃', '▄', '▅', '▆', '▇', '█', '▇', '▆', '▅', '▄', '▃', '▂', '▁'],
        'shade' => ['░', '▒', '▓', '█', '▓', '▒', '░'],
        'arc' => ['◜', '◝', '◞', '◟'],
        'circle' => ['◐', '◓', '◑', '◒'],
    ];

    /** @var string[] */
    private array $frames;
    private string $finishedIndicator = '';
    private int $frame = 0;
    private bool $running = false;
    private bool $finished = false;
    private PeriodicStepper $frameStepper;

    private string $message;

    public function __construct(
        string $message = 'Loading...',
    ) {
        $this->message = StringUtils::stripControlBytes(StringUtils::sanitizeUtf8($message));
        $this->frames = self::$styles[self::DEFAULT_STYLE];
        $this->frameStepper = PeriodicStepper::everyMs(self::DEFAULT_INTERVAL_MS, 8);
    }

    /**
     * Start the loader animation.
     */
    public function start(): void
    {
        if ($this->running) {
            return;
        }

        $this->running = true;
        $this->finished = false;
        $this->frame = 0;
        $this->frameStepper->reset();
        $this->invalidate();
        $this->getContext()?->requestRender();
        $this->startScheduledTick($this->frameStepper->getIntervalSeconds());
    }

    /**
     * Stop the loader animation.
     */
    public function stop(): void
    {
        $wasRunning = $this->running;
        $this->running = false;
        $this->finished = true;

        if ($wasRunning) {
            $this->clearScheduledTick();
        }

        $this->invalidate();
        $this->getContext()?->requestRender();
    }

    /**
     * Check if the loader is running.
     */
    public function isRunning(): bool
    {
        return $this->running;
    }

    /**
     * @return $this
     */
    public function setMessage(string $message): static
    {
        $message = StringUtils::stripControlBytes(StringUtils::sanitizeUtf8($message));

        if ($this->message !== $message) {
            $this->message = $message;
            $this->invalidate();
            $this->getContext()?->requestRender();
        }

        return $this;
    }

    /**
     * @param string[] $frames Frame characters (at least 2)
     */
    public static function addSpinner(string $name, array $frames): void
    {
        $frames = array_values($frames);

        if (\count($frames) < 2) {
            throw new InvalidArgumentException('Must have at least 2 indicator frame characters.');
        }

        self::$styles[$name] = $frames;
    }

    /**
     * @return $this
     */
    public function setSpinner(string $name): static
    {
        if (!isset(self::$styles[$name])) {
            throw new InvalidArgumentException(\sprintf('Unknown loader style "%s". Available styles: "%s".', $name, implode('", "', array_keys(self::$styles))));
        }

        $this->frames = self::$styles[$name];
        $this->frame = 0;
        $this->invalidate();

        return $this;
    }

    /**
     * @return $this
     */
    public function setIntervalMs(int $intervalMs): static
    {
        $this->frameStepper->setIntervalMs($intervalMs);

        if ($this->running) {
            $this->startScheduledTick($this->frameStepper->getIntervalSeconds());
        }

        return $this;
    }

    /**
     * @return $this
     */
    public function setFinishedIndicator(string $finishedIndicator): static
    {
        $this->finishedIndicator = $finishedIndicator;
        $this->invalidate();

        return $this;
    }

    /**
     * Get the current message.
     */
    public function getMessage(): string
    {
        return $this->message;
    }

    /**
     * Get the current spinner frame character.
     */
    public function getSpinnerFrame(): string
    {
        return $this->frames[$this->frame];
    }

    /**
     * Advance the animation frame if enough time has passed.
     *
     * @return bool True if the frame was advanced
     */
    public function tick(?float $deltaTime = null): bool
    {
        if (!$this->running) {
            return false;
        }

        if (0 === $steps = $this->frameStepper->advance($deltaTime)) {
            return false;
        }

        $this->frame = ($this->frame + $steps) % \count($this->frames);
        $this->invalidate();

        return true;
    }

    /**
     * @return string[]
     */
    public function render(RenderContext $context): array
    {
        $columns = $context->getColumns();

        if ($this->running) {
            $indicator = $this->frames[$this->frame];
        } elseif ($this->finished && '' !== $this->finishedIndicator) {
            $indicator = $this->finishedIndicator;
        } else {
            return [];
        }

        $styledIndicator = $this->applyElement('spinner', $indicator);
        $styledMessage = $this->applyElement('message', $this->message);

        $content = $styledIndicator.' '.$styledMessage;
        $line = AnsiUtils::truncateToWidth($content, $columns);

        $visibleLen = AnsiUtils::visibleWidth($line);
        $rightFill = str_repeat(' ', max(0, $columns - $visibleLen));

        return ['', $line.$rightFill];
    }

    protected function onAttach(WidgetContext $context): void
    {
        if ($this->running) {
            $this->frameStepper->reset();
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
}
