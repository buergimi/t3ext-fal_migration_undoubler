<?php
namespace MaxServ\FalMigrationUndoubler\Command;

/**
 *  Copyright notice
 *
 *  ⓒ 2016 ⊰ ℳichiel ℛoos ⊱ <michiel@maxserv.com>
 *  All rights reserved
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is free
 *  software; you can redistribute it and/or modify it under the terms of the
 *  GNU General Public License as published by the Free Software Foundation;
 *  either version 2 of the License, or (at your option) any later version.
 *
 *  The GNU General Public License can be found at
 *  http://www.gnu.org/copyleft/gpl.html.
 *
 *  This script is distributed in the hope that it will be useful, but
 *  WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY
 *  or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for
 *  more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 */

use TYPO3\CMS\Core\Resource\Exception\FileOperationErrorException;
use TYPO3\CMS\Core\Resource\Exception\InsufficientFileAccessPermissionsException;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Undouble tasks
 *
 * @since 1.0.0
 * @package MaxServ\FalMigrationUndoubler
 * @subpackage Controller
 */
class UndoubleCommandController extends AbstractCommandController
{
    /**
     * @var \TYPO3\CMS\Core\Resource\ResourceStorage
     */
    protected $storage;

    /**
     * Normalize _migrated folder
     *
     * De-duplication of files in _migrated folder. Relations to files in migrated folder will be re-linked to an
     * identical file outside of the _migrated folder.
     *
     * @since 1.0.0
     *
     * @param bool $dryRun
     *
     * @return void
     */
    public function MigratedFilesCommand($dryRun = false)
    {
        $this->headerMessage('Normalizing _migrated folder');
        $this->init();
        $counter = 0;
        $freedBytes = 0;
        try {
            $result = $this->getDocumentsWithMatchingSha1();
            $total = $this->databaseConnection->sql_num_rows($result);
            $this->infoMessage(
                'Found ' . $total . ' records in _migrated folder that have counterparts outside of there'
            );
            while ($record = $this->databaseConnection->sql_fetch_assoc($result)) {
                $progress = number_format(100 * ($counter++ / $total), 1) . '% of ' . $total;
                $this->infoMessage($progress . ' Updating references for ' . $record['oldIdentifier']);
                if (!$dryRun) {
                    $this->updateReferencesToFile($record);
                }
                $this->successMessage('Removing file from _migrated folder.');
                if (!$dryRun) {
                    try {
                        $this->storage->deleteFile($this->storage->getFile($record['oldIdentifier']));
                        $freedBytes += $record['size'];
                    } catch (FileOperationErrorException $error) {
                        $this->errorMessage($error->getMessage());
                    } catch (InsufficientFileAccessPermissionsException $error) {
                        $this->errorMessage($error->getMessage());
                        $this->errorMessage('Please edit the _cli_lowlevel user in the backend and ensure this user has access to the _migrated filemount.');
                        exit();
                    }
                } else {
                    $freedBytes += $record['size'];
                }
            }
            $this->databaseConnection->sql_free_result($result);
            $this->horizontalLine('info');
            $this->message('Removed ' . $this->successString($total) . ' files from the _migrated folder.');
            $this->message('Freed ' . $this->successString(GeneralUtility::formatSize($freedBytes)));
        } catch (\RuntimeException $exception) {
            $this->errorMessage($exception->getMessage());
        }
    }

    /**
     * Initialize the storage repository.
     */
    public function init()
    {
        /** @var $storageRepository \TYPO3\CMS\Core\Resource\StorageRepository */
        $storageRepository = GeneralUtility::makeInstance('TYPO3\\CMS\\Core\\Resource\\StorageRepository');
        $storages = $storageRepository->findAll();
        $this->storage = $storages[0];
    }

    /**
     * Get database result pointer to sys_file records in the _migrated folder with sha1 matching documents outside of
     * the _migrated folder.
     *
     * @throws \RuntimeException
     * @return \mysqli_result
     */
    protected function getDocumentsWithMatchingSha1()
    {
        $result = $this->databaseConnection->sql_query('SELECT
            migrated.size,
            migrated.uid AS oldUid,
            alternate.uid AS newUid,
            migrated.identifier AS oldIdentifier,
            alternate.identifier AS newIdentifier
        FROM sys_file AS migrated
            JOIN sys_file AS alternate
                ON migrated.sha1 = alternate.sha1
        WHERE
            NOT migrated.uid = alternate.uid
            AND migrated.identifier LIKE "/_migrated/%"
            AND alternate.identifier NOT LIKE "/_migrated/%"
        GROUP BY
            migrated.uid
        ;');
        if ($result === null) {
            throw new \RuntimeException('Database query failed. Error was: ' . $this->databaseConnection->sql_error());
        }

        return $result;
    }

    /**
     * Move file references from migrated file to other file, mark migrated file as deleted
     *
     * @param array $identifiers
     *
     * @return void
     */
    protected function updateReferencesToFile($identifiers)
    {
        $result = $this->databaseConnection->exec_UPDATEquery(
            'sys_file_reference',
            'uid_local = ' . (int)$identifiers['oldUid'],
            array('uid_local' => (int)$identifiers['newUid'])
        );
        if ($result === null) {
            throw new \RuntimeException('Database query failed. Error was: ' . $this->databaseConnection->sql_error());
        }
    }
}
