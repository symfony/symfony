| Q             | A
| ------------- | ---
| Branch?       | 3.4 or master / 2.7, 2.8 or 3.3 <!-- see comment below -->
| Bug fix?      | yes/no
| New feature?  | yes/no <!-- don't forget to update src/**/CHANGELOG.md files -->
| BC breaks?    | yes/no
| Deprecations? | yes/no <!-- don't forget to update UPGRADE-*.md files -->
| Tests pass?   | yes/no
| Fixed tickets | #... <!-- #-prefixed issue number(s), if any -->
| License       | MIT
| Doc PR        | symfony/symfony-docs#... <!--highly recommended for new features-->

<!--
- Bug fixes must be submitted against the lowest branch where they apply
  (lowest branches are regularly merged to upper ones so they get the fixes too).
- Features and deprecations must be submitted against the 3.4,
  legacy code removals go to the master branch.
- Please fill in this template according to the PR you're about to submit.
- Replace this comment by a description of what your PR is solving.
-->
