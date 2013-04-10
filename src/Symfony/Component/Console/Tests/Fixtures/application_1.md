UNKNOWN
=======

* help
* list

help
----

* Description: Displays help for a command
* Usage: `help [--format="..."] [--raw] [command_name]`
* Aliases: <none>

The <info>help</info> command displays help for a given command:

  <info>php app/console help list</info>

You can also output the help in other formats by using the <comment>--format</comment> option:

  <info>php app/console help --format=xml list</info>

To display the list of available commands, please use the <info>list</info> command.

### Arguments:

**command_name:**

* Name: command_name
* Is required: no
* Is array: no
* Description: The command name
* Default: `'help'`

### Options:

**format:**

* Name: `--format`
* Shortcut: <none>
* Accept value: yes
* Is value required: yes
* Is multiple: no
* Description: To output help in other formats.
* Default: `NULL`

**raw:**

* Name: `--raw`
* Shortcut: <none>
* Accept value: no
* Is value required: no
* Is multiple: no
* Description: To output raw command list.
* Default: `false`

list
----

* Description: Lists commands
* Usage: `list [--format="..."] [--raw] [namespace]`
* Aliases: <none>

The <info>list</info> command lists all commands:

  <info>php app/console list</info>

You can also display the commands for a specific namespace:

  <info>php app/console list test</info>

You can also output the information in other formats by using the <comment>--format</comment> option:

  <info>php app/console list --format=xml</info>

It's also possible to get raw list of commands (useful for embedding command runner):

  <info>php app/console list --raw</info>

### Arguments:

**namespace:**

* Name: namespace
* Is required: no
* Is array: no
* Description: The namespace name
* Default: `NULL`

### Options:

**format:**

* Name: `--format`
* Shortcut: <none>
* Accept value: yes
* Is value required: yes
* Is multiple: no
* Description: To output help in other formats.
* Default: `NULL`

**raw:**

* Name: `--raw`
* Shortcut: <none>
* Accept value: no
* Is value required: no
* Is multiple: no
* Description: To output raw command list.
* Default: `false`
