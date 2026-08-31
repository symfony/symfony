Shell completion tests
======================

These tests run the completion scripts of `src/Symfony/Component/Console/Resources/`
in real bash, zsh and fish shells, and compare the suggestions with the expectations
of `cases.txt`.

Running the tests
-----------------

With Docker, which provides the three shells and Composer:

```bash
Tests/Fixtures/shell-completion/docker-run.sh       # all shells
Tests/Fixtures/shell-completion/docker-run.sh zsh   # one shell
```

Directly, using the shells installed on the machine (`composer install` must have
been run in the Console component first). Missing shells are skipped:

```bash
Tests/Fixtures/shell-completion/run.sh
Tests/Fixtures/shell-completion/run.sh bash
```

Files
-----

* `app`: the console application used as a fixture, with commands, options and
  suggested values covering the tricky cases (spaces, colons, backslashes);
* `cases.txt`: the command lines to complete and their expected suggestions;
* `drivers/`: one driver per shell, turning a command line into a list of
  suggestions;
* `run.sh`: dumps the completion script with `app completion <shell>`, then runs
  every applicable case through the driver of the shell.

Note: the Docker run installs the Composer dependencies of the component for
the PHP version of the image (8.4), overwriting `vendor/`.
