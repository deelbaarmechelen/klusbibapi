<?php
namespace Tests\DbUnit;

use \PHPUnit_Extensions_Database_Operation_IDatabaseOperation;
use \PHPUnit_Extensions_Database_DB_IDatabaseConnection;
use \PHPUnit_Extensions_Database_DataSet_IDataSet;
use \PHPUnit_Extensions_Database_Operation_Exception;
use \PDOException;

/**
 * Executes a truncate against all tables in a dataset.
 *
 * @package    DbUnit
 * @author     Mike Lively <m@digitalsandwich.com>
 * @copyright  2010-2013 Mike Lively <m@digitalsandwich.com>
 * @license    http://www.opensource.org/licenses/BSD-3-Clause  The BSD 3-Clause License
 * @version    Release: @package_version@
 * @link       http://www.phpunit.de/
 * @since      Class available since Release 1.0.0
 */
class PHPUnit_Extensions_Database_Operation_Restart_Seq implements PHPUnit_Extensions_Database_Operation_IDatabaseOperation
{

    public function execute(PHPUnit_Extensions_Database_DB_IDatabaseConnection $connection, PHPUnit_Extensions_Database_DataSet_IDataSet $dataSet)
    {
        foreach ($dataSet->getReverseIterator() as $table) {
            /* @var $table PHPUnit_Extensions_Database_DataSet_ITable */
            $sequenceName = $this->unquote($connection->quoteSchemaObject($table->getTableMetaData()->getTableName())) .
                    "_id_seq";
            $query = " ALTER SEQUENCE IF EXISTS \"".$sequenceName."\" RESTART";
                //{$connection->getTruncateCommand()} {$connection->quoteSchemaObject($table->getTableMetaData()->getTableName())}
            try {
                $connection->getConnection()->query($query);
            } catch (PDOException $e) {
                throw new PHPUnit_Extensions_Database_Operation_Exception('RESTART_SEQ', $query, array(), $table, $e->getMessage());
            }
        }
    }
    
    private function unquote($quotedString) {
        
        return substr($quotedString, 1,-1);
    }
}