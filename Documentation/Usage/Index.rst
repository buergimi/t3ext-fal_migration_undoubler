.. ==================================================
.. FOR YOUR INFORMATION
.. --------------------------------------------------
.. -*- coding: utf-8 -*- with BOM.

.. include:: ../Includes.txt



.. _usage:

=====
Usage
=====

Ensure the _cli_lowlevel user has the proper permissions
========================================================

The user running the command-line task must have persmissions to remove files from the `_migrated` folder. You will need to edit the `_cli_lowlevel` user in the backend and grant him/her access to a filemount containing the `_migrated` folder. If you don't do this, the files can not be deleted and the migration will exit.

.. figure:: ../Images/Edit_filemount.png
   :alt: Create a filemount with access to the _migrated folder

   Create a filemount with access to the _migrated folder

.. figure:: ../Images/Edit_cli_lowlevel.png
   :alt: Attach the filemount to the _cli_lowlevel user

   Attach the filemount to the _cli_lowlevel user

Call the command line task
==========================

First do a dry-run to see what will be re-referenced and how much space you can save.

.. code-block:: bash

    php ./typo3/cli_dispatch.phpsh extbase undouble:migratedfiles --dry-run

If that output looks good:

.. code-block:: bash

   php ./typo3/cli_dispatch.phpsh extbase undouble:migratedfiles