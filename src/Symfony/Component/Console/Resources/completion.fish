# This file is part of the Symfony package.
#
# (c) Fabien Potencier <fabien@symfony.com>
#
# For the full copyright and license information, please view
# https://symfony.com/doc/current/contributing/code/license.html

function _sf_{{ COMMAND_NAME }}
    set sf_cmd (commandline -o)
    set c (count (commandline -oc))

    # fish completes the whole "--option=value" token, so the option is prepended
    # to the suggestions to let fish filter them
    set flag_prefix ""
    if [ (count $sf_cmd) -gt $c ]; and string match -qr '^-[^=]*=' -- $sf_cmd[-1]
        set flag_prefix (string replace -r '=.*$' '=' -- $sf_cmd[-1])
    end

    set completecmd "$sf_cmd[1]" "_complete" "--no-interaction" "-sfish" "-a{{ VERSION }}"

    for i in $sf_cmd
        if [ $i != "" ]
            set completecmd $completecmd "-i$i"
        end
    end

    set completecmd $completecmd "-c$c"

    set sfcomplete (env SHELL_VERBOSITY=0 $completecmd)

    # the first line can ask to complete the paths or the directories along with the values
    set paths ""
    if contains -- "$sfcomplete[1]" :paths :directories
        set paths $sfcomplete[1]
        set -e sfcomplete[1]
    end

    for i in $sfcomplete
        echo "$flag_prefix$i"
    end

    # fish completes the paths, after the "=" of an option. "complete -C" reads the
    # token as typed, while __fish_complete_path ignores its quotes before fish 3.7.
    set token (commandline -ct)
    set token (string sub -s (math (string length -- "$flag_prefix") + 1) -- "$token")
    if [ "$paths" = ":paths" ]
        for i in (complete -C"'' $token")
            echo "$flag_prefix$i"
        end
    else if [ "$paths" = ":directories" ]
        for i in (__fish_complete_directories "$token")
            echo "$flag_prefix$i"
        end
    end
end

complete -c '{{ COMMAND_NAME }}' -a '(_sf_{{ COMMAND_NAME }})' -f
