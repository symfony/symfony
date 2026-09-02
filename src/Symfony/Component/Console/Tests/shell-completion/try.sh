#!/usr/bin/env bash

# This file is part of the Symfony package.
#
# (c) Fabien Potencier <fabien@symfony.com>
#
# For the full copyright and license information, please view
# https://symfony.com/doc/current/contributing/code/license.html

# Opens an interactive shell with the completion of the fixture application
# installed, to try it by hand with a real TAB key.
#
# Usage: try.sh <bash|zsh|fish>

set -eu

dir="$(cd "$(dirname "$0")" && pwd)"
shell="${1:-bash}"

if ! command -v "$shell" > /dev/null; then
    echo "The \"$shell\" shell is not installed." >&2

    exit 1
fi

home="$(mktemp -d)"
trap 'rm -rf "$home"' EXIT

mkdir -p "$home/bin"
ln -s "$dir/app" "$home/bin/app"
chmod +x "$dir/app"

export PATH="$home/bin:$PATH"

app completion "$shell" > "$home/completion.$shell"

cat <<EOF
The "app" fixture application is in the PATH and its $shell completion is loaded.
Type TAB to complete, for instance:

  app demo:<TAB>
  app demo:hello --format=<TAB>
  app demo:special <TAB>

Exit the shell to come back.

EOF

case "$shell" in
    bash)
        bash_completion=''
        # the Linux locations, then Homebrew's bash-completion@2 and 1.x
        for candidate in \
            /usr/share/bash-completion/bash_completion \
            /etc/bash_completion \
            /opt/homebrew/etc/profile.d/bash_completion.sh \
            /opt/homebrew/etc/bash_completion \
            /usr/local/etc/profile.d/bash_completion.sh \
            /usr/local/etc/bash_completion \
        ; do
            if [ -f "$candidate" ]; then
                bash_completion="$candidate"
                break
            fi
        done

        if [ -z "$bash_completion" ]; then
            echo 'The bash completion requires the "bash-completion" package, which is not installed.' >&2

            exit 1
        fi

        cat > "$home/bashrc" <<EOF
source $bash_completion
source $home/completion.bash
export PATH="$PATH"
PS1='try-bash\$ '
EOF
        exec bash --rcfile "$home/bashrc" -i
        ;;
    zsh)
        cat > "$home/.zshrc" <<EOF
autoload -Uz compinit && compinit -u
source $home/completion.zsh
export PATH="$PATH"
PS1='try-zsh%# '
EOF
        ZDOTDIR="$home" exec zsh -i
        ;;
    fish)
        mkdir -p "$home/config/fish"
        cat > "$home/config/fish/config.fish" <<EOF
set -gx PATH $home/bin \$PATH
source $home/completion.fish
function fish_prompt
    echo 'try-fish> '
end
EOF
        XDG_CONFIG_HOME="$home/config" exec fish -i
        ;;
esac
