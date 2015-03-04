Symfony mechanism for exploring and dumping PHP variables
=========================================================

This component provides a mechanism that allows exploring then dumping
any PHP variable.

It handles scalars, objects and resources properly, taking hard and soft
references into account. More than being immune to infinite recursion
problems, it allows dumping where references link to each other.
It explores recursive structures using a breadth-first algorithm.

The component exposes all the parts involved in the different steps of
cloning then dumping a PHP variable, while applying size limits and having
specialized output formats and methods.
