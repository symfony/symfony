# This file is part of the Symfony package.
#
# (c) Fabien Potencier <fabien@symfony.com>
#
# For the full copyright and license information, please view
# https://symfony.com/doc/current/contributing/code/license.html

# Loads the bash-completion package, which provides the parsing of the command
# line used by the completion script. Meant to be sourced.

# the profile.d files only load the package for an interactive shell
PS1='$ '

# the Linux locations, then Homebrew's bash-completion@2 and 1.x
for bash_completion in \
    /usr/share/bash-completion/bash_completion \
    /etc/bash_completion \
    /opt/homebrew/etc/profile.d/bash_completion.sh \
    /opt/homebrew/etc/bash_completion \
    /usr/local/etc/profile.d/bash_completion.sh \
    /usr/local/etc/bash_completion \
; do
    if [ -f "$bash_completion" ]; then
        # shellcheck disable=SC1090
        source "$bash_completion"
        break
    fi
done

if ! declare -F _get_comp_words_by_ref > /dev/null; then
    echo "bash-completion is not installed" >&2
    exit 1
fi
