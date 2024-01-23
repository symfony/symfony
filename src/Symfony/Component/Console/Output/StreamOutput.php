<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Console\Output;

use Symfony\Component\Console\Exception\InvalidArgumentException;
use Symfony\Component\Console\Formatter\OutputFormatterInterface;

/**
 * StreamOutput writes the output to a given stream.
 *
 * Usage:
 *
 *     $output = new StreamOutput(fopen('php://stdout', 'w'));
 *
 * As `StreamOutput` can use any stream, you can also use a file:
 *
 *     $output = new StreamOutput(fopen('/path/to/output.log', 'a', false));
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class StreamOutput extends Output
{
    /** @var resource */
    private $stream;

    /**
     * @param resource                      $stream    A stream resource
     * @param int                           $verbosity The verbosity level (one of the VERBOSITY constants in OutputInterface)
     * @param bool|null                     $decorated Whether to decorate messages (null for auto-guessing)
     * @param OutputFormatterInterface|null $formatter Output formatter instance (null to use default OutputFormatter)
     *
     * @throws InvalidArgumentException When first argument is not a real stream
     */
    public function __construct($stream, int $verbosity = self::VERBOSITY_NORMAL, ?bool $decorated = null, ?OutputFormatterInterface $formatter = null)
    {
        if (!\is_resource($stream) || 'stream' !== get_resource_type($stream)) {
            throw new InvalidArgumentException('The StreamOutput class needs a stream as its first argument.');
        }

        $this->stream = $stream;

        $decorated ??= $this->hasColorSupport();

        parent::__construct($verbosity, $decorated, $formatter);
    }

    /**
     * Gets the stream attached to this StreamOutput instance.
     *
     * @return resource
     */
    public function getStream()
    {
        return $this->stream;
    }

    protected function doWrite(string $message, bool $newline): void
    {
        if ($newline) {
            $message .= \PHP_EOL;
        }

        @fwrite($this->stream, $message);

        fflush($this->stream);
    }

    /**
     * Returns true if the stream supports colorization.
     *
     * Colorization is disabled if not supported by the stream:
     *
     * This is tricky on Windows, because Cygwin, Msys2 etc emulate pseudo
     * terminals via named pipes, so we can only check the environment.
     *
     * Reference: Composer\XdebugHandler\Process::supportsColor
     * https://github.com/composer/xdebug-handler
     *
     * @return bool true if the stream supports colorization, false otherwise
     */
    protected function hasColorSupport(): bool
    {
        // Follow https://no-color.org/
        if (isset($_SERVER['NO_COLOR']) || false !== getenv('NO_COLOR')) {
            return false;
        }

        if (!$this->isTty()) {
            return false;
        }

        if (\DIRECTORY_SEPARATOR === '\\'
            && \function_exists('sapi_windows_vt100_support')
            && @sapi_windows_vt100_support($this->stream)
        ) {
            return true;
        }

        return 'Hyper' === getenv('TERM_PROGRAM')
            || false !== getenv('ANSICON')
            || 'ON' === getenv('ConEmuANSI')
            || str_starts_with((string) getenv('TERM'), 'xterm');
    }

    /**
     * Checks if the stream is a TTY, i.e; whether the output stream is connected to a terminal.
     *
     * Reference: Composer\Util\Platform::isTty
     * https://github.com/composer/composer
     */
    private function isTty(): bool
    {
        // Detect msysgit/mingw and assume this is a tty because detection
        // does not work correctly, see https://github.com/composer/composer/issues/9690
        if (\in_array(strtoupper((string) getenv('MSYSTEM')), ['MINGW32', 'MINGW64'], true)) {
            return true;
        }

        // Modern cross-platform function, includes the fstat fallback so if it is present we trust it
        if (\function_exists('stream_isatty')) {
            return stream_isatty($this->stream);
        }

        // Only trusting this if it is positive, otherwise prefer fstat fallback.
        if (\function_exists('posix_isatty') && posix_isatty($this->stream)) {
            return true;
        }

        $stat = @fstat($this->stream);

        // Check if formatted mode is S_IFCHR
        return $stat ? 0020000 === ($stat['mode'] & 0170000) : false;
    }
}
