UNKNOWN
=======

* help
* list

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
