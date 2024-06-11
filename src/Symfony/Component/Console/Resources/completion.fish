# This file is part of the Symfony package.
#
# (c) Fabien Potencier <fabien@symfony.com>
#
# For the full copyright and license information, please view
# https://symfony.com/doc/current/contributing/code/license.html

function _sf_{{ COMMAND_NAME }}
    set sf_cmd (commandline -o)
    set c (count (commandline -oc))

    # _SF_CMD allows Symfony CLI to tell us to use a different command to run the console
    if set -q _SF_CMD; and test -n _SF_CMD
      for i in $_SF_CMD
          if [ $i != "" ]
              set completecmd $completecmd "$i"
          end
      end
    else
      set completecmd $completecmd $sf_cmd[1]
    end

    set completecmd $completecmd "_complete" "--no-interaction" "-sfish" "-a{{ VERSION }}"

    for i in $sf_cmd
        if [ $i != "" ]
            set completecmd $completecmd "-i$i"
        end
    end

    set completecmd $completecmd "-c$c"

    $completecmd
end

complete -c '{{ COMMAND_NAME }}' -a '(_sf_{{ COMMAND_NAME }})' -f
