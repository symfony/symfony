#!/usr/bin/env bash

# This file is part of the Symfony package.
#
# (c) Fabien Potencier <fabien@symfony.com>
#
# For the full copyright and license information, please view
# https://symfony.com/doc/current/contributing/code/license.html

# Runs the shell completion tests in Docker, with the three shells installed.
#
# Usage:
#   docker.sh run [bash|zsh|fish|all]     run the tests, on every shell by default
#   docker.sh try [bash|zsh|fish]         open a shell to try the completion by hand

set -eu

dir="$(cd "$(dirname "$0")" && pwd)"
root="$(cd "$dir/../../../../../.." && pwd)"
fixture=src/Symfony/Component/Console/Tests/shell-completion
command="${1:-run}"

# The dependencies are only installed when the fixture cannot run, because this
# resolves them for the PHP version of the image and rewrites vendor/ of the
# Console component.
install_dependencies="
    if ! $fixture/app list > /dev/null 2>&1; then
        export COMPOSER_ROOT_VERSION=\$(grep ' VERSION = ' src/Symfony/Component/HttpKernel/Kernel.php | cut -d \"'\" -f2 | cut -d '.' -f 1-2).x-dev
        composer update --no-interaction --no-progress --working-dir=src/Symfony/Component/Console
    fi
"

case "$command" in
    run|try) ;;
    *)
        sed -n '10,15p' "$0" | cut -c 3-
        exit 1
        ;;
esac

docker build --quiet --tag symfony-shell-completion-tests "$dir"

if [ "$command" = 'run' ]; then
    exec docker run --rm --tty --volume "$root":/symfony symfony-shell-completion-tests bash -c "
        set -e
        $install_dependencies
        $fixture/run.sh ${2:-all}
    "
fi

if [ ! -t 0 ]; then
    echo 'The "try" command needs a terminal, run it directly from a shell.' >&2

    exit 1
fi

exec docker run --rm --interactive --tty --volume "$root":/symfony symfony-shell-completion-tests bash -c "
    set -e
    $install_dependencies
    $fixture/try.sh ${2:-bash}
"
