<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Console\Tests\Resources;

use PHPUnit\Framework\TestCase;

/**
 * @group doot
 */
class BashCompletionTest extends TestCase
{
    protected static $completionScript;

    public static function setUpBeforeClass(): void
    {
        self::$completionScript = realpath(__DIR__.'/../../Resources/completion.bash');
    }

    public function testHasCompletionFunction()
    {
        // This is more of a test of the test environment than a test of the
        // bash script; still useful if some joker changes the CI image
        $required = [
            '__ltrim_colon_completions',
            '_get_comp_words_by_ref',
        ];
        $missing = [];
        $prereq_command = $this->getPrerequisiteLoader();
        foreach ($required as $func) {
            $commands = [
                $prereq_command,
                'type -t '.escapeshellarg($func),
            ];
            $bash_command = implode("\n", $commands);
            $command = 'bash -e -c '.escapeshellarg($bash_command).' 2>&1';
            exec($command, $output, $exit_val);
            if (0 !== $exit_val) {
                $missing[] = $func;
            }
        }
        $this->assertEmpty($missing);
    }

    private function getPrerequisiteLoader(): string
    {
        $out = [
            // Turn on programmable completion
            'shopt -s progcomp',
        ];
        $possibles = [
            // Paths to where required shell functions reside
            '/etc/bash_completion',
            '/usr/share/bash-completion/bash_completion',
        ];

        foreach ($possibles as $path) {
            if (is_file($path) && !is_dir($path)) {
                $out[] = '. '.escapeshellarg($path);
                break;
            }
        }

        return implode("\n", $out);
    }

    /**
     * Retrieve the contents of the resource script being tested.
     *
     * @param string $command The command name to setup completion for
     */
    private function getScriptLoader(string $command): string
    {
        $replacements = [
            '/\{\{ COMMAND_NAME \}\}/' => $command,
        ];

        // Dump, then include (inside bash) from a temporary file lest it
        // become too big to fit on the command line
        $temp = tempnam(sys_get_temp_dir(), 'completion.bash');
        register_shutdown_function(function () use ($temp) {
            if (file_exists($temp)) {
                unlink($temp);
            }
        });

        file_put_contents(
            $temp,
            preg_replace(array_keys($replacements), array_values($replacements),
                file_get_contents(self::$completionScript)
            )
        );

        return '. '.escapeshellarg($temp);
    }

    protected function getCompletionLoader(string $command): string
    {
        return implode("\n", [
            $this->getPrerequisiteLoader(),
            $this->getScriptLoader($command),
        ]);
    }

    public function testIssue47780()
    {
        // Tests an issue where IFS was being overridden, causing shell to
        // misbehave
        $after = [
            '_sf_Issue47780 || true',
            'LS_OPTIONS="-a -l"',
            'ls $LS_OPTIONS',
        ];
        $bash_command = implode("\n", [
            $this->getCompletionLoader('Issue47780'),
            ...$after,
        ]);
        $summary = '<COMPLETION SCRIPT>; '.implode("\n", $after);
        $command = 'bash -e -c '.escapeshellarg($bash_command).' 2>&1';
        exec($command, $output, $exit_val);
        $this->assertSame(0, $exit_val,
            "Command `$summary` failed: ".implode("\n", $output)
        );
    }
}
