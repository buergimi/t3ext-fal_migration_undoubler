# TYPO3 extension: fal_migration_undoubler
Finds documents outside of the _migrated folder, updates references to point to those files and removes the redundant file from the _migrated folder.

*_Please do not use yet! The migrations in link and RTE fields are missing_*

Clone it

```bash
git clone https://github.com/MaxServ/t3ext-fal_migration_undoubler.git fal_migration_undoubler
```

Or install it using composer:

```bash
composer config repositories.fal_migration_undoubler vcs https://github.com/MaxServ/t3ext-fal_migration_undoubler.git
composer require maxserv/fal_migration_undoubler
```
More information on [usage](Documentation/Usage/Index.rst) can be found in the [documentation folder](Documentation/Index.rst).

##Usage

###Ensure the _cli_lowlevel user has the proper permissions

The user running the command-line task must have persmissions to remove files from the `_migrated` folder. You will need to edit the `_cli_lowlevel` user in the backend and grant him/her access to a filemount containing the `_migrated` folder.

![Create a filemount with access to the _migrated folder](/Documentation/Images/Edit_filemount.png)

![Attach the filemount to the _cli_lowlevel user](/Documentation/Images/Edit_cli_lowlevel.png)

###Call the command line task

First do a dry-run to see what will be re-referenced and how much space you can save.

```bash
php ./typo3/cli_dispatch.phpsh extbase undouble:migratedfiles --dry-run
```

If that output looks good:

```bash
php ./typo3/cli_dispatch.phpsh extbase undouble:migratedfiles
```

## License & Disclaimer
Copyright 2016 Michiel Roos - MaxServ B.V.

This Source Code Form is subject to the terms of the GNU General Public License as published by the Free Software Foundation; either version 2 of the License, or (at your option) any later version. If a copy of the GPL was not distributed with this file, You can obtain one at http://www.gnu.org/copyleft/gpl.html

BECAUSE THE PROGRAM IS LICENSED FREE OF CHARGE, THERE IS NO WARRANTY FOR THE PROGRAM, TO THE EXTENT PERMITTED BY APPLICABLE LAW. EXCEPT WHEN OTHERWISE STATED IN WRITING THE COPYRIGHT HOLDERS AND/OR OTHER PARTIES PROVIDE THE PROGRAM "AS IS" WITHOUT WARRANTY OF ANY KIND, EITHER EXPRESSED OR IMPLIED, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE. THE ENTIRE RISK AS TO THE QUALITY AND PERFORMANCE OF THE PROGRAM IS WITH YOU. SHOULD THE PROGRAM PROVE DEFECTIVE, YOU ASSUME THE COST OF ALL NECESSARY SERVICING, REPAIR OR CORRECTION.

IN NO EVENT UNLESS REQUIRED BY APPLICABLE LAW OR AGREED TO IN WRITING WILL ANY COPYRIGHT HOLDER, OR ANY OTHER PARTY WHO MAY MODIFY AND/OR REDISTRIBUTE THE PROGRAM AS PERMITTED ABOVE, BE LIABLE TO YOU FOR DAMAGES, INCLUDING ANY GENERAL, SPECIAL, INCIDENTAL OR CONSEQUENTIAL DAMAGES ARISING OUT OF THE USE OR INABILITY TO USE THE PROGRAM (INCLUDING BUT NOT LIMITED TO LOSS OF DATA OR DATA BEING RENDERED INACCURATE OR LOSSES SUSTAINED BY YOU OR THIRD PARTIES OR A FAILURE OF THE PROGRAM TO OPERATE WITH ANY OTHER PROGRAMS), EVEN IF SUCH HOLDER OR OTHER PARTY HAS BEEN ADVISED OF THE POSSIBILITY OF SUCH DAMAGES.
