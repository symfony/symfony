CHANGELOG
=========

4.0.0
-----

 * environment variables will always be inherited
 * added a second `array $env = array()` argument to the `start()`, `run()`,
   `mustRun()`, and `restart()` methods of the `Process` class
 * added a second `array $env = array()` argument to the `start()` method of the
   `PhpProcess` class
 * the `ProcessUtils::escapeArgument()` method has been removed
 * the `areEnvironmentVariablesInherited()`, `getOptions()`, and `setOptions()`
   methods of the `Process` class have been removed
 * support for passing `proc_open()` options has been removed
 * removed the `ProcessBuilder` class, use the `Process` class instead
 * removed the `getEnhanceWindowsCompatibility()` and `setEnhanceWindowsCompatibility()` methods of the `Process` class
 * passing a not existing working directory to the constructor of the `Symfony\Component\Process\Process` class is not
   supported anymore

3.4.0
-----

 * deprecated the ProcessBuilder class
 * deprecated calling `Process::start()` without setting a valid working directory beforehand (via `setWorkingDirectory()` or constructor)

3.3.0
-----

 * added command line arrays in the `Process` class
 * added `$env` argument to `Process::start()`, `run()`, `mustRun()` and `restart()` methods
 * deprecated the `ProcessUtils::escapeArgument()` method
 * deprecated not inheriting environment variables
 * deprecated configuring `proc_open()` options
 * deprecated configuring enhanced Windows compatibility
 * deprecated configuring enhanced sigchild compatibility

2.5.0
-----

 * added support for PTY mode
 * added the convenience method "mustRun"
 * deprecation: Process::setStdin() is deprecated in favor of Process::setInput()
 * deprecation: Process::getStdin() is deprecated in favor of Process::getInput()
 * deprecation: Process::setInput() and ProcessBuilder::setInput() do not accept non-scalar types

2.4.0
-----

 * added the ability to define an idle timeout

2.3.0
-----

 * added ProcessUtils::escapeArgument() to fix the bug in escapeshellarg() function on Windows
 * added Process::signal()
 * added Process::getPid()
 * added support for a TTY mode

2.2.0
-----

 * added ProcessBuilder::setArguments() to reset the arguments on a builder
 * added a way to retrieve the standard and error output incrementally
 * added Process:restart()

2.1.0
-----

 * added support for non-blocking processes (start(), wait(), isRunning(), stop())
 * enhanced Windows compatibility
 * added Process::getExitCodeText() that returns a string representation for
   the exit code returned by the process
 * added ProcessBuilder
