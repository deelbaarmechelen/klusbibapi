<?php
/*
 *     Copyright (C) 2015 Bernard Butaye
 * 
 *     This program is free software: you can redistribute it and/or modify
 *     it under the terms of the GNU Affero General Public License as
 *     published by the Free Software Foundation, either version 3 of the
 *     License, or (at your option) any later version.
 * 
 *     This program is distributed in the hope that it will be useful,
 *     but WITHOUT ANY WARRANTY; without even the implied warranty of
 *     MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *     GNU Affero General Public License for more details.
 * 
 *     You should have received a copy of the GNU Affero General Public License
 *     along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */
namespace Tests\DbUnit;

use \Tests\DbUnit\PHPUnit_Extensions_Database_Operation_Restart_Seq;
use \PHPUnit_Extensions_Database_DB_IDatabaseConnection;
use \PHPUnit_Extensions_Database_DefaultTester;
use \PHPUnit_Extensions_Database_Operation_Factory;
use \PHPUnit_Extensions_Database_Operation_Composite;

/**
 * Description of PHPUnit_Extensions_Database_PostgresTester
 *
 * @author Bernard Butaye
 */
class PHPUnit_Extensions_Database_PostgresTester extends PHPUnit_Extensions_Database_DefaultTester {
    
    /**
     * Creates a new default database tester using the given connection.
     *
     * @param PHPUnit_Extensions_Database_DB_IDatabaseConnection $connection
     */
    public function __construct(PHPUnit_Extensions_Database_DB_IDatabaseConnection $connection)
    {
        parent::__construct($connection);

        $this->setSetUpOperation(self::CLEAN_RESTART_INSERT());
    }
    
    /**
     * Returns an restart sequence database operation.
     *
     * @return PHPUnit_Extensions_Database_Operation_IDatabaseOperation
     */
    public static function RESTART_SEQ()
    {
        return new PHPUnit_Extensions_Database_Operation_Restart_Seq();
    }
    
    /**
     * Returns an composite database operation.
     *
     * @return PHPUnit_Extensions_Database_Operation_IDatabaseOperation
     */
    public static function CLEAN_RESTART_INSERT($cascadeTruncates = FALSE)
    {
        return new PHPUnit_Extensions_Database_Operation_Composite(array(
            PHPUnit_Extensions_Database_Operation_Factory::TRUNCATE($cascadeTruncates),
            self::RESTART_SEQ(),
            PHPUnit_Extensions_Database_Operation_Factory::INSERT()
        ));
    }
}
