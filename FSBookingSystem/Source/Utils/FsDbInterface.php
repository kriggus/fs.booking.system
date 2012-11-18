<?php

namespace FSBookingSys\Utils;

/**
 * MySql database connection class.
 * 
 * Provides a collection of functions used to write or read to a MySql database.
 * Implements the singelton pattern.
 * 
 *  @author kristofer
 */

class FsDbInterface
{
    private $host;
    private $dbUser;
    private $dbPass;
    private $dbName;
    private $dbConn;
    private $connectError;
    
    private static $instance;
    
    /**
     * Return a instance of FsDbInterface.
     *
     * @param  string $host			
     * @param  string $dbUser  		
     * @param  string $dbPass		
     * @param  string $dbName
     * @return \FSBookingSys\Utils\FsDbInterface
     * @access public
     */
    public static function getInstance($host, $dbUser, $dbPass, $dbName)
    {
        if ( is_null( self::$instance ) )
        {
            self::$instance = new self($host, $dbUser, $dbPass, $dbName);
        }
        return self::$instance;
    }
    
    /**
     * FsDbInterface constructor
     *
     * @param  string $host			
     * @param  string $dbUser  		
     * @param  string $dbPass		
     * @param  string $dbName
     * @access private
     */
    private function __construct ($host, $dbUser, $dbPass, $dbName)
    {
        $this->host = $host;
        $this->dbUser = $dbUser;
        $this->dbPass = $dbPass;
        $this->dbName = $dbName;
        $this->connectToDb();    
    }
    
    /**
     * Connects to MySQL server and selects given database
     * 
     * @return void
     * @access private
     */
    private function connectToDb()
    {
    	// connects to given MySQL server. Sets error if it fails.
        if (! $this->dbConn = @mysql_connect($this->host, $this->dbUser, $this->dbPass)) 
        {
            trigger_error('Could not connect to server');
            $this->connectError = true;
        }
        // selects given database. Sets error if it fails.
        else if (! @mysql_select_db($this->dbName, $this->dbConn)) 
        {
            trigger_error('Could not select database');
            $this->connectError = true;
        }
    } 
    
    
    /**
     * Checks if a error has occured. 
     * 
     * Comment1: Must to be done manually by the external user (that is, another class) 
     *           as we lack event handling. 
     *           //Kristofer
     * Comment2: Can be extended to save the error message should we want to
     *           //Kristofer
     *
     * @return boolean
     * @access public
     */
    public function isError() 
    {
        if($this->connectError) 
        {
            return true;
        }
        
        $error = mysql_error($this->dbConn);
        if (empty($error)) 
        {
            return false;
        }
        else 
        {
            return true;
        }
    }
    /**
     * Executes a sql query and return the result
     *  
     * @param string $sql
     * @return \FSBookingSys\Utils\FsSqlResult
     * @access private
     */
    private function query($sql)
    {    
        if(! $queryResource = mysql_query($sql, $this->dbConn)) 
            trigger_error('Query failed: ' . mysql_error($this->dbConn) . 'SQL: ' . $sql);
        return new FsSqlResult($this, $queryResource);   
    }
    
    /**
     * Try to insert a record into a table.
     * 
     * @param string $record
     * @param string $table
     * @return boolean
     * @access public
     */
    public function insertRecord($record, $table)
    {
        $sql = "INSERT INTO
                    " . $table .
                    " VALUES ('" . 
                        implode("','", (array_map ($this->safeEscapeString, $record))) .
                    "')";
        
        $this->query($sql);
        
        if($this->isError()) 
            return false;
            
        return true;
    }
    
    /**
     * Try to delete a record from a table.
     * 
     * @param string $keyColumn
     * @param string $keyValue
     * @param string $table
     * @return boolean
     * @access public
     */
    
    public function deleteRecord($keyColumn, $keyValue, $table)
    {
    	
	    $sql = "DELETE FROM
	                " . $table . "
	            WHERE
	                " . $keyColumn . "='" . $this->safeEscapeString($keyValue) . "'";
		
	    $result = $this->query($sql);
	    if($this->isError() || $result->affected() == 0)
	        return false;
	        
	    return true;
    }
    
    /**
     * Escape string (database record) to avoid SQL injection attacks. 
     * 
     * Comment1:    REMEMBER TO USE in you functions when inserting into the database. 
     *              //Kristofer
     *              
     * @param string $str
     * @return boolean|string
     */
    private function safeEscapeString ($str)
    {
        if(get_magic_quotes_gpc()) 
            return $str;
        else 
            return mysql_real_escape_string($str);
    }
}

/**
 * Class for fetching SQL resluts.
 * 
 * @access public
 * @author kristofer
 */
class FsSqlResult 
{
    private $fsDbInterface;
    private $query;
    
    /**
     * 
     * @param FsDbInterface  $fsDbInterface
     * @param resource query $query (php mysql resource)
     * @access public
     */
    public function __constructor(&$fsDbInterface, $query)
    {
        $this->fsDbInterface = $fsDbInterface;
        $this->query = $query;
    }
    
    /**
     * Fetches all rows from the result. 
     * 
     * @return array	(Can be empty)
     * @access public
     */
    public function fetchAllRows()
    {
        $arr = array();
        while($row = $this->fetchRow())
        {
            array_push($arr, array_values($row));
        }
        return $arr;
    }
    
    /**
     * Return size (number of rows) of current sql result
     * 
     * @return number
     * @access public
     */
    public function size() 
    {
        return mysql_num_rows($this->query);
    }
    
    /**
     * Return number of affected rows in previous sql operation.
     *
     * @return number
     * @access pubilc 
     */
    public function affected()
    {
    	return mysql_affected_rows($this->query);
    }
    
    /**
     * Fetches a row from the result.
     * 
     * @return array|boolean
     * @access public
     */
    public function fetchRow()
    {
        if($row = mysql_fetch_array($this->query, MYSQL_ASSOC)) 
        {
            return $row;
        }
        else if ( $this->size() > 0 ) 
        {
            mysql_data_seek($this->query, 0);
            return false;
        }
        else 
        {
            return false;
        }   
    }
    
    /**
     * Checks if a error has occured
     *
     * @return boolean
     * @access public
     */
    public function isError()
    {
        return $this->fsDbInterface->isError();
    }
}

?>