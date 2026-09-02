Shell completion tests
======================

These tests run the completion scripts of `src/Symfony/Component/Console/Resources/`
in real bash, zsh and fish shells, and compare the suggestions with the expectations
of `cases.txt`.

Running the tests
-----------------

With Docker, which provides the three shells:

```bash
Tests/shell-completion/docker.sh run       # all shells
Tests/shell-completion/docker.sh run zsh   # one shell
```

Directly, using the shells installed on the machine (`composer install` must have
been run in the Console component first). Missing shells are skipped:

```bash
Tests/shell-completion/run.sh
Tests/shell-completion/run.sh bash
```

Trying the completion by hand
-----------------------------

To type a real TAB instead of reading assertions, open a shell with the fixture
application and its completion loaded:

```bash
Tests/shell-completion/docker.sh try zsh   # in Docker
Tests/shell-completion/try.sh zsh              # on the machine
```

Then complete, for instance, `app demo:<TAB>`, `app demo:hello --format=<TAB>` or
`app demo:special <TAB>`. Exit the shell to come back.

