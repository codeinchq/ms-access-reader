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
        $this->checkShellCommands();
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
    private function checkShellCommands():void
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
     * @return string
     */
    private function getBoundary():string
    {
        return '--'.uniqid('boundary').'--';
    }

    /**
     * @return \Generator|string[]
     */
    public function listTables():\Generator
    {
        $boundary = $this->getBoundary();
        $tablesList = shell_exec('mdb-tables -d'.escapeshellarg($boundary).' '.escapeshellarg($this->dbPath));
        foreach (explode($boundary, trim($tablesList)) as $table) {
            if (!empty($table)) {
                yield $table;
            }
        }
    }

    /**
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
     * @param string $table
     * @return \Generator|string[]
     */
    public function exportDataToSql(string $table):\Generator
    {
        $boundary = $this->getBoundary();
        $data = shell_exec('mdb-export -H -R'.escapeshellarg($boundary).' -I mysql '
            .escapeshellarg($this->dbPath).' '.escapeshellarg($table));
        foreach (explode($boundary, $data) as $query) {
            if (!empty($query)) {
                yield $query;
            }
        }
    }

    /**
     * @param string $table
     * @return \Generator|array[]
     */
    public function exportDataToArray(string $table):\Generator
    {
        $data = shell_exec('mdb-export -H '.escapeshellarg($this->dbPath).' '.escapeshellarg($table));
        foreach (explode("\n", $data) as $line) {
            if (!empty($line)) {
                yield str_getcsv($line, ',', '"');
            }
        }
    }
}