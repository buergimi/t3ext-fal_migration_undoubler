.. ==================================================
.. FOR YOUR INFORMATION
.. --------------------------------------------------
.. -*- coding: utf-8 -*- with BOM.

.. include:: ../../Includes.txt



.. _what-does-it-do:

================
What does it do?
================

*TL;DR; Finds documents outside of the _migrated folder, updates references to point to those files and removes the redundant file from the _migrated folder.*

When you have an old TYPO3 installation, you may have once allowed your users do upload files directly in content elements. These files would then be stored in the 'uploads' folder. Maybe you decided later on that this was not such a good idea and you disabled the direct upload using TSConfig. Your users used the fileadmin from then on.

When upgrading to a more recent TYPO3 version from something pre-6, ALL your files will be migrated to a FAL enabled storage. It may be the case that you end up with a lot of files in the '_migrated' folder in the root of your FAL storage, that are also present elsewhere in your storage.

We can check by the sha1 value of the file record which files in the _migrated folder have a duplicate and update the references to the _migrated file to point to the duplicate file. After updating the references we can safely delete the migrated file.

This may save you a couple of GB's of space.