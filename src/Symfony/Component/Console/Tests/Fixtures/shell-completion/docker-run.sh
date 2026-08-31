#!/usr/bin/env bash

# This file is part of the Symfony package.
#
# (c) Fabien Potencier <fabien@symfony.com>
#
# For the full copyright and license information, please view
# https://symfony.com/doc/current/contributing/code/license.html

# Runs the shell completion tests in Docker, with all the shells installed.
#
# Usage: docker-run.sh [bash|zsh|fish|all]

set -eu

dir="$(cd "$(dirname "$0")" && pwd)"
root="$(cd "$dir/../../../../../../.." && pwd)"
shells="${1:-all}"

docker build --quiet --tag symfony-shell-completion-tests "$dir"

# The dependencies are resolved for the PHP version of the image, which is the
# one used by the CI. This rewrites vendor/ of the Console component: run
# "composer install" again to work on the host.
docker run --rm --tty --volume "$root":/symfony symfony-shell-completion-tests bash -c "
    set -e
    export COMPOSER_ROOT_VERSION=\$(grep ' VERSION = ' src/Symfony/Component/HttpKernel/Kernel.php | cut -d \"'\" -f2 | cut -d '.' -f 1-2).x-dev
    composer update --no-interaction --no-progress --working-dir=src/Symfony/Component/Console
    src/Symfony/Component/Console/Tests/Fixtures/shell-completion/run.sh $shells
"
