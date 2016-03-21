.. ==================================================
.. FOR YOUR INFORMATION
.. --------------------------------------------------
.. -*- coding: utf-8 -*- with BOM.

.. include:: ../Includes.txt



.. _known-problems:

==============
Known problems
==============

Files in storage with uid 0
===========================
Christopher lets us know: https://github.com/MaxServ/t3ext-fal_migration_undoubler/issues/1

On some installations, files exist in the sys_file table that are attached to storage 0. I have seen entries in storage 0 in several installations. These installs had exactly one storage in the backend and that one has ID 1. They never had another one. Additionally, having a storage with sys_file_storage.uid=0 is technically impossible as this uid is an integer with auto_increment, where the first value to be inserted is 1 and not 0. I think the fact that entries in storage 0 are there, is a bug.

In the installations I checked that with, these entries were not referenced anywhere, so for me, the solution was to run:

.. code-block:: sql

	DELETE FROM `sys_file` WHERE `storage` = 0 AND `identifier` LIKE '/fileadmin/_migrated/%';

Afterwards, the extension worked correctly.

Report problemns
================
If you find any problems, please report them to us by email.

Please take some time and make a proper report stating at least:

- version of the extension
- reproducibility
- steps to reproduce
- observed behavior
- expected behavior

Writing a good bug report will help us to fix the bugs faster and better.