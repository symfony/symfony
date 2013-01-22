CHANGELOG
=========

2.2.0
-----

 * [BC BREAK] modified the timeout methods: you should now set the timeout to 0
   instead of null to disable it. Using null for setters still works, however the
   getters will return 0 instead of null when the timeout is disabled.
 * [BC BREAK] added a second argument to ProcessBuilder::add() to force unescaping.
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
