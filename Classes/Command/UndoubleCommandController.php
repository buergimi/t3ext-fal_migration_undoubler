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
     * Is this a dry run?
     *
     * @var bool
     */
    protected $isDryRun = false;

    /**
     * @var \TYPO3\CMS\Core\Resource\ResourceStorage
     */
    protected $storage;

    /**
     * Rich Text prepared queries
     *
     * @var array<PreparedStatement>
     */
    protected $richTextStatements = [];

    /**
     * Initialize the storage repository.
     */
    public function __construct()
    {
        parent::__construct();
        /** @var $storageRepository \TYPO3\CMS\Core\Resource\StorageRepository */
        $storageRepository = GeneralUtility::makeInstance('TYPO3\\CMS\\Core\\Resource\\StorageRepository');
        $storages = $storageRepository->findAll();
        $this->storage = $storages[0];
    }

    /**
     * Normalize _migrated folder
     *
     * De-duplication of files in _migrated folder. Relations to files in migrated folder will be re-linked to an
     * identical file outside of the _migrated folder.
     *
     * @since 1.0.0
     *
     * @param bool $dryRun Do a test run, no modifications.
     *
     * @return void
     */
    public function MigratedFilesCommand($dryRun = false)
    {
        $this->headerMessage('Normalizing _migrated folder');
        $this->isDryRun = $dryRun;
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
                if (!$this->isDryRun) {
                    $this->updateReferencesToFile($record);
                }
                $this->successMessage('Removing file from _migrated folder.');
                if (!$this->isDryRun) {
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
     * Normalize references made from rich text fields
     *
     * @since 1.1.0
     *
     * @param string $table The table to work on. Default: `tt_content`.
     * @param string $field The field to work on. Default: `bodytext`.
     * @param bool $dryRun Do a test run, no modifications.
     *
     */
    public function RichTextReferencesCommand($table = 'tt_content', $field = 'bodytext', $dryRun = false)
    {
        $this->headerMessage('Normalizing references from ' . $table . ' -> ' . $field);

        $table = preg_replace('/[^a-zA-Z0-9_-]/', '', $table);
        $field = preg_replace('/[^a-zA-Z0-9_-]/', '', $field);
        $this->isDryRun = $dryRun;
        $counter = 0;
        $freedBytes = 0;
        $result = $this->getDocumentsWithMatchingSha1();
        $total = $this->databaseConnection->sql_num_rows($result);
        $this->infoMessage(
            'Found ' . $total . ' records in _migrated folder that have counterparts outside of there'
        );
        while ($record = $this->databaseConnection->sql_fetch_assoc($result)) {
            $progress = number_format(100 * ($counter++ / $total), 1) . '% of ' . $total;
            $richTextRecords = $this->getRichTextFieldsWithReferences($table, $field, $record['oldIdentifier']);
            if (count($richTextRecords)) {
                $this->infoMessage('Found ' . count($richTextRecords) . ' ' . $table . ' records that have a "<link>" tag in the field ' . $field);
                $this->infoMessage($progress . ' Updating references for ' . $record['oldIdentifier']);
                $this->updateRichTextFields($table, $field, $richTextRecords, $record);
            }
        }
        $this->databaseConnection->sql_free_result($result);
        $this->horizontalLine('info');
        $this->message('Removed ' . $this->successString($total) . ' files from the _migrated folder.');
        $this->message('Freed ' . $this->successString(GeneralUtility::formatSize($freedBytes)));
    }

    /**
     * Get database result pointer to sys_file records in the _migrated folder with sha1 matching documents outside of
     * the _migrated folder.
     *
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
            $this->errorMessage('Database query failed. Error was: ' . $this->databaseConnection->sql_error());
        }

        return $result;
    }

    /**
     * Get rich text fields with references
     *
     * @param string $table
     * @param string $field
     * @param int $uid
     *
     * @return mixed
     */
    private function getRichTextFieldsWithReferences($table, $field, $uid)
    {
        $rows = [];
        $uid = (int)$uid;

        $key = $table . '-' . $field;
        if (!isset($this->richTextStatements[$key])) {
            $this->richTextStatements[$table . '-' . $field] = $this->databaseConnection->prepare_SELECTquery(
                'uid, ' . $field,
                $table,
                'deleted=0 AND (' . $field . ' LIKE ? OR ' . $field . ' LIKE ?)'
            );
        }
        $this->richTextStatements[$key]->execute(array(
            '"%<link file:' . $uid . '%"',
            '"%&lt;link file:' . $uid . '%"'
        ));

        $result = $this->richTextStatements[$key]->fetch();
        while ($row = $this->databaseConnection->sql_fetch_assoc($result)) {
            $rows[] = $row;
        }
        $this->databaseConnection->sql_free_result($result);
        if ($rows === null) {
            $this->errorMessage('Database query failed. Error was: ' . $this->databaseConnection->sql_error());
        } else {
            $result = $rows;
        }

        return $result;
    }

    /**
     * Update rich text fields with new references
     *
     * @param string $table
     * @param string $field
     * @param array $richTextRecords
     * @param array $migrationData
     *
     * @return mixed
     */
    private function updateRichTextFields($table, $field, array $richTextRecords, array $migrationData)
    {
        $table = $this->databaseConnection->escapeStrForLike($table, $table);
        $field = $this->databaseConnection->escapeStrForLike($field, $table);
        $result = [];

        foreach ($richTextRecords as $rec) {
            $originalContent = $rec[$field];
            $finalContent = $originalContent;
            $results = array();
            preg_match_all(
                '/(?:<link file ([0-9]+)([^>]*)?>(.*?)<\/link file>|&lt;link file ([0-9]+)(.*?)?&gt;(.*?)&lt;\/link file&gt;)/',
                $originalContent,
                $results,
                PREG_SET_ORDER
            );
            if (count($results)) {
                foreach ($results as $result) {
                    $searchString = $result[0];
                    $linkClass = '';
                    $linkTarget = '';
                    $linkText = '';
                    $linkTitle = '';
                    // Match for <link file
                    if ((int)$result[1] > 0) {
                        // see EXT:dam/link filetag/class.tx_dam_rtetransform_link filetag.php
                        list($linkTarget, $linkClass, $linkTitle) = explode(' ', trim($result[2]), 3);
                        $linkText = $result[3];
                    }
                    // Match for &lt;link file
                    $useEntities = ((int)$result[4] > 0);
                    if ($useEntities) {
                        // see EXT:dam/link filetag/class.tx_dam_rtetransform_link filetag.php
                        list($linkTarget, $linkClass, $linkTitle) = explode(' ', trim($result[5]), 3);
                        $linkText = $result[6];
                    }
                    $openingBracket = $useEntities ? '&lt;' : '<';
                    $closingBracket = $useEntities ? '&gt;' : '>';
                    $replaceString = $openingBracket . 'link file:' . $migrationData['oldUid'] . ' ' . $linkTarget . ' ' . $linkClass . ' ' . $linkTitle . ' ' . $closingBracket . $linkText . $openingBracket . '/link' . $closingBracket;
                    $finalContent = str_replace($searchString, $replaceString, $finalContent);
                }
                // update the record
                if ($finalContent !== $originalContent) {
                    if (!$this->isDryRun) {
                        $this->databaseConnection->exec_UPDATEquery(
                            $table,
                            'uid=' . $rec['uid'],
                            array($field => $finalContent)
                        );

                    }
                    $this->infoMessage('Updated ' . $table . ':' . $rec['uid'] . ' with: ' . $finalContent);
                }
            }
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
            $this->errorMessage('Database query failed. Error was: ' . $this->databaseConnection->sql_error());
        }
    }
}
