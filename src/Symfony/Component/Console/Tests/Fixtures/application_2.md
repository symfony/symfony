My Symfony application
======================

* alias1
* alias2
* help
* list

**descriptor:**

* descriptor:command1
* descriptor:command2

help
----

* Description: Displays help for a command
* Usage: `help`
* Aliases: <none>

The <info>help</info> command displays help for a given command:

  <info>php /usr/bin/phpunit help list</info>

You can also output the help as XML by using the <comment>--xml</comment> option:

  <info>php /usr/bin/phpunit help --xml list</info>

To display the list of available commands, please use the <info>list</info> command.

list
----

* Description: Lists commands
* Usage: `list`
* Aliases: <none>

The <info>list</info> command lists all commands:

  <info>php /usr/bin/phpunit list</info>

You can also display the commands for a specific namespace:

  <info>php /usr/bin/phpunit list test</info>

You can also output the information as XML by using the <comment>--xml</comment> option:

  <info>php /usr/bin/phpunit list --xml</info>

It's also possible to get raw list of commands (useful for embedding command runner):

  <info>php /usr/bin/phpunit list --raw</info>

descriptor:command1
-------------------

* Description: command 1 description
* Usage: `descriptor:command1`
* Aliases: `alias1`, `alias2`

command 1 help

descriptor:command2
-------------------

* Description: command 2 description
* Usage: `descriptor:command2 [-o|--option_name] argument_name`
* Aliases: <none>

command 2 help

### Arguments:

**argument_name:**

* Name: argument_name
* Is required: yes
* Is array: no
* Description: <none>
* Default: `NULL`

### Options:

**option_name:**

* Name: `--option_name`
* Shortcut: `-o`
* Accept value: no
* Is value required: no
* Is multiple: no
* Description: <none>
* Default: `false`
