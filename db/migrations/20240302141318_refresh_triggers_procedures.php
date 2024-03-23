<?php

use Illuminate\Database\Capsule\Manager as Capsule;

require_once __DIR__ . '/../../app/env.php';
require_once __DIR__ . '/../../app/settings.php';
/**
 * Custom template for database migration with Illuminate\Database
 * 
 * Default template can be found at https://github.com/robmorgan/phinx/blob/master/src/Phinx/Migration/Migration.template.php.dist
 */
class RefreshTriggersProcedures extends AbstractCapsuleMigration
{
    /**
     * Up Method.
     *
     * Called when invoking migrate
     *
     * More information on writing migrations is available here:
     * http://docs.phinx.org/en/latest/migrations.html#the-abstractmigration-class
     *
     * Remember to call "create()" or "update()" and NOT "save()" when working
     * with the Table class.
     */
	public function up()
	{
        $this->initCapsule();

        // create functions and procedures
        $this->runDoubleDollarDelimitedStatements(__DIR__ . "/../dump/20240302-inventory-procedures.sql");
        $this->runDoubleDollarDelimitedStatements(__DIR__ . "/../dump/20240302-klusbibdb-procedures.sql");
        $this->runDoubleDollarDelimitedStatements(__DIR__ . "/../dump/20240302-inventory-triggers.sql");
        $this->runDoubleDollarDelimitedStatements(__DIR__ . "/../dump/20240302-klusbibdb-triggers.sql");
	}

    /**
     * Down Method.
     *
     * Called when invoking rollback
     */
	public function down()
	{
	}

    private function runDoubleDollarDelimitedStatements($fileName)
    {
        $sql = file_get_contents($fileName);
        $sqlStmts = explode("$$", $sql);
        foreach($sqlStmts as $sqlStmt) {
            if (strlen(trim($sqlStmt)) == 0) {
                continue;
            }
            $this->multiQueryOnPDO($sqlStmt);
        }
    }
    private function multiQueryOnPDO($sql)
    {
      $db = Capsule::connection()->getPdo();
  
      // works regardless of statements emulation
      $db->setAttribute(PDO::ATTR_EMULATE_PREPARES, 0);
      $db->exec($sql);    
    }    
}