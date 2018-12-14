<?php
//
// +---------------------------------------------------------------------+
// | CODE INC. SOURCE CODE                                               |
// +---------------------------------------------------------------------+
// | Copyright (c) 2018 - Code Inc. SAS - All Rights Reserved.           |
// | Visit https://www.codeinc.fr for more information about licensing.  |
// +---------------------------------------------------------------------+
// | NOTICE:  All information contained herein is, and remains the       |
// | property of Code Inc. SAS. The intellectual and technical concepts  |
// | contained herein are proprietary to Code Inc. SAS are protected by  |
// | trade secret or copyright law. Dissemination of this information or |
// | reproduction of this material is strictly forbidden unless prior    |
// | written permission is obtained from Code Inc. SAS.                  |
// +---------------------------------------------------------------------+
//
// Author:   Joan Fabrégat <joan@codeinc.fr>
// Date:     15/11/2018
// Project:  MsAccessReader
//
declare(strict_types=1);
namespace CodeInc\MsAccessReader;

/**
 * Class AccessReader
 *
 * @package CodeInc\MsAccessReader
 * @author Joan Fabrégat <joan@codeinc.fr>
 */
class AccessReader
{
    /**
     * @var string
     */
    private $dbPath;

    /**
     * AccessReader constructor.
     *
     * @param string $dbPath
     */
    public function __construct(string $dbPath)
    {
        $this->setDbPath($dbPath);
        $this->errorIfMissingShellCommands();
    }

    /**
     * @param string $dbPath
     * @throws \RuntimeException
     */
    private function setDbPath(string $dbPath):void
    {
        if (!file_exists($dbPath)) {
            throw new \RuntimeException(
                sprintf("The Access DB file '%s' does not exist", $dbPath)
            );
        }
        $this->dbPath = $dbPath;
    }

    /**
     * @throws \RuntimeException
     */
    private function errorIfMissingShellCommands():void
    {
        foreach (['mdb-tables', 'mdb-schema', 'mdb-export'] as $command) {
            if (empty(shell_exec('which '.escapeshellarg($command)))) {
                throw new \RuntimeException(
                    sprintf("The command '%s' is missing. "
                        ."Please check if the mdbtools (https://sourceforge.net/projects/mdbtools/) packages is installed",
                        $command)
                );
            }
        }
    }

    /**
     * Lists tables.
     *
     * @return \Generator|string[]
     */
    public function listTables():\Generator
    {
        $delimiter = uniqid('boundary_');
        $tablesList = shell_exec('mdb-tables -d'.escapeshellarg($delimiter).' '.escapeshellarg($this->dbPath));
        foreach (explode($delimiter, trim($tablesList)) as $table) {
            if (!empty($table)) {
                yield $table;
            }
        }
    }

    /**
     * Exports a table's schema to SQL.
     *
     * @param string $table
     * @param bool $dropTable
     * @param string $backend
     * @return string
     */
    public function exportSchemaToSql(string $table, bool $dropTable = false, string $backend = 'mysql'):string
    {
        $schema = shell_exec('mdb-schema --default-values'.($dropTable ? ' --drop-table' : '')
            .' -T '.escapeshellarg($table).' '.escapeshellarg($this->dbPath).' '.escapeshellarg($backend));
        if ($backend == 'mysql') {
            $schema = preg_replace_callback("/varchar ?\\(([0-9]+)\\)/ui", function (array $matches):string {
                return $matches[1] > 255 ? 'text' : $matches[0];
            }, $schema);
        }
        return $schema;
    }

    /**
     * Exports a table's data to SQL.
     *
     * @param string $table
     * @return \Generator|string[]
     */
    public function exportDataToSql(string $table):\Generator
    {
        $delimiter = uniqid('boundary_');
        $data = shell_exec('mdb-export -H -R'.escapeshellarg($delimiter).' -I mysql '
            .escapeshellarg($this->dbPath).' '.escapeshellarg($table));
        foreach (explode($delimiter, $data) as $query) {
            if (!empty($query)) {
                yield $query;
            }
        }
    }

    /**
     * Exports a table's content to an array.
     *
     * @param string $table
     * @return \Generator|array[]
     */
    public function exportDataToArray(string $table):\Generator
    {
        $colDelimiter = '|';
        $rowDelimiter = uniqid("row_");
        $data = shell_exec('mdb-export -H -d'.escapeshellarg($colDelimiter).' -R'.escapeshellarg($rowDelimiter)
            .' '.escapeshellarg($this->dbPath).' '.escapeshellarg($table));
        foreach (explode($rowDelimiter, $data) as $line) {
            if (!empty($line)) {
                yield str_getcsv($line, $colDelimiter, '"');
            }
        }
    }
}