MbString åpplicätion
====================

Table of Contents
-----------------



- `help`_
- `list`_

descriptor
~~~~~~~~~~



- `descriptor:åèä`_

Commands
--------

Global
~~~~~~

help
....

Display help for a command

Usage
^^^^^

- ``help [--format FORMAT] [--raw] [--] [<command_name>]``

The help command displays help for a given command:

  %%PHP_SELF%% help list

You can also output the help in other formats by using the --format option:

  %%PHP_SELF%% help --format=xml list

To display the list of available commands, please use the list command.

Arguments
^^^^^^^^^

command_name

Options
^^^^^^^

\-\-format
""""""""""

The output format (txt, xml, json, or md)

- **Accept value**: yes
- **Is value required**: yes
- **Is multiple**: no
- **Is negatable**: no
- **Default**: ``'txt'``

\-\-raw
"""""""

To output raw command help

- **Accept value**: no
- **Is value required**: no
- **Is multiple**: no
- **Is negatable**: no
- **Default**: ``false``



list
....

List commands

Usage
^^^^^

- ``list [--raw] [--format FORMAT] [--short] [--] [<namespace>]``

The list command lists all commands:

  %%PHP_SELF%% list

You can also display the commands for a specific namespace:

  %%PHP_SELF%% list test

You can also output the information in other formats by using the --format option:

  %%PHP_SELF%% list --format=xml

It's also possible to get raw list of commands (useful for embedding command runner):

  %%PHP_SELF%% list --raw

Arguments
^^^^^^^^^

namespace

Options
^^^^^^^

\-\-raw
"""""""

To output raw command list

- **Accept value**: no
- **Is value required**: no
- **Is multiple**: no
- **Is negatable**: no
- **Default**: ``false``

\-\-format
""""""""""

The output format (txt, xml, json, or md)

- **Accept value**: yes
- **Is value required**: yes
- **Is multiple**: no
- **Is negatable**: no
- **Default**: ``'txt'``

\-\-short
"""""""""

To skip describing commands' arguments

- **Accept value**: no
- **Is value required**: no
- **Is multiple**: no
- **Is negatable**: no
- **Default**: ``false``



descriptor
~~~~~~~~~~

descriptor:åèä
..............

command åèä description

Usage
^^^^^

- ``descriptor:åèä [-o|--option_åèä] [--] <argument_åèä>``
- ``descriptor:åèä -o|--option_name <argument_name>``
- ``descriptor:åèä <argument_name>``

command åèä help

Arguments
^^^^^^^^^

argument_åèä

Options
^^^^^^^

\-\-option_åèä|-o
"""""""""""""""""

- **Accept value**: no
- **Is value required**: no
- **Is multiple**: no
- **Is negatable**: no
- **Default**: ``false``
