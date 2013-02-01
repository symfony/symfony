CHANGELOG
=========

2.2.0
-----
 * deprecated isArray() from both InputOption and InputArgument, replaced by
   isValueMultiple() and isMultiple().
 * deprecated InputOption::VALUE_IS_ARRAY in favor of InputOption::VALUE_MULTIPLE
 * deprecated InputArgument::IS_ARRAY in favor of InputArgument::MULTIPLE
 * added support for colorization on Windows via ConEmu
 * added a method to Dialog Helper to ask for a question and hide the response
 * added support for interactive selections in console (DialogHelper::select())
 * added support for autocompletion as you type in Dialog Helper

2.1.0
-----

 * added ConsoleOutputInterface
 * added the possibility to disable a command (Command::isEnabled())
 * added suggestions when a command does not exist
 * added a --raw option to the list command
 * added support for STDERR in the console output class (errors are now sent
   to STDERR)
 * made the defaults (helper set, commands, input definition) in Application
   more easily customizable
 * added support for the shell even if readline is not available
 * added support for process isolation in Symfony shell via
   `--process-isolation` switch
 * added support for `--`, which disables options parsing after that point
   (tokens will be parsed as arguments)
