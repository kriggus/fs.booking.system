<?php

/**
 * MySql database connection class.
 * 
 * Provides a collection of functions used to write or read to a MySql database.
 * Implements the singelton pattern.
 * 
 *  @author kristofer
 *
 *  Example usage:
 *  	//Connects to localhost as root and adds a record in the table bs_customers
 *  	$db = DbInterface::getInstance('localhost', 'root', 'passw0rd', 'database', 'bs_')
 *  	$db->insertRecord(array('Simon', 19, 'simon@for...'), 'customers');
 *  	if (!$db->isError()) {
 *  		echo 'Customer added';
 *  	}
 *  	
 */

class DbInterface
{
	private $dbConn;
	private $tablePrefix;

	private static $instance;

	/**
	 * Return a instance of DbInterface.
	 * If you can be _sure_ that you've already connected to
	 * the database you can use this function without parameters:
	 * $db = DbInterface::getInstance();
	 *
	 * @param  string $srv			
	 * @param  string $uname  		
	 * @param  string $pass		
	 * @param  string $db
	 * @param  string $tblPrefix
	 * @return DbInterface
	 * @access public
	 */
	public static function getInstance($srv=NULL, $uname=NULL, $pass=NULL, $db=NULL, $tblPrefix=NULL)
	{
		if (is_null(self::$instance)) {
			$t = new self();
			self::$instance = $t;
			
			$srv				= is_null($srv)		? 'localhost'	: $srv;
			$uname				= is_null($uname)	? 'root'		: $uname;
			$pass				= is_null($pass)	? 'password' 	: $pass;
			$db					= is_null($db)		? 'database'	: $db;
			$t->tablePrefix		= is_null($db)		? ''			: $tblPrefix;
	
			if (! $t->dbConn = @mysql_connect($srv, $uname, $pass)) {
				trigger_error('Could not connect to database server');
			} else if (! @mysql_select_db($db, $t->dbConn)) {
				trigger_error('Could not select database');
				$t->dbConn = false;
			} else {
				mysql_set_charset('utf8', $t->dbConn);	//Remember to change this to the correct charset!
			}
		}

		return self::$instance;
	}

	/**
	 * FsDbInterface constructor.
	 * Note that this is private and empty. Use DbInterface::getInstance() instead
	 *
	 * @access private
	 */
	private function __construct() { }


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
		return ($this->dbConn === false || mysql_error($this->dbConn) != '');
	}

	/**
	 * Executes a sql query and return the result
	 *  
	 * @param string $sql
	 * @return SqlResult
	 * @access private
	 */
	public function query($sql)
	{    
		if(! $result = mysql_query($sql, $this->dbConn)) 
			trigger_error('Query failed: ' . mysql_error($this->dbConn) . 'SQL: ' . $sql);
		return new SqlResult($this, $result);   
	}

	/**
	 * Tries to insert a record into a table.
	 * 
	 * @param string $record
	 * @param string $table
	 * @return boolean
	 * @access public
	 */
	public function insertRecord($record, $table)
	{
		$values = array_map(array(get_class($this), 'safeEscapeString'), $record);
		$valStr = implode("','", $values);
		$sql =	"INSERT INTO `" . $this->tablePrefix . $table .
				"` VALUES ('" . $valStr . "')";

		$this->query($sql);
		return !$this->isError();
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
			" . $this->tablePrefix . $table . "
			WHERE
			" . $keyColumn . "='" . $this->safeEscapeString($keyValue) . "'";

		$result = $this->query($sql);
		return !($this->isError() || $result->affected() == 0);
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
	public static function safeEscapeString($str)
	{
		if(get_magic_quotes_gpc()) {
			return mysql_real_escape_string(stripslashes($str));
			//This isn't as stupid as it looks. Some characters aren't properly escaped with magic_quotes.
		} else {
			return mysql_real_escape_string($str);
		}
	}
}

/* ******************************************************************************************* */

/**
 * Class for fetching SQL resluts.
 * 
 * @access public
 * @author kristofer
 */
class SqlResult 
{
	private $affectedRows = 0;
	private $DbInterface;
	private $query;
	private $error;

	/**
	 * @param DbInterface  $DbInterface
	 * @param resource query $query (php mysql resource)
	 * @access public
	 */
	public function __construct(&$DbInterface, $query)
	{
		$this->DbInterface = $DbInterface;
		$this->query = $query;
		$this->error = $DbInterface->isError();
		if (is_bool($query)) {
			//If the SQL-query is of the type UPDATE/DELETE/etc. the result will
			//be a bool and not a mysql result, if that's the case we need to count
			//the rows immediately!
			$this->affectedRows = mysql_affected_rows();
		}
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
		while($row = $this->fetchRow()) {
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
		if (is_bool($this->query)) {
			return -1;
		} else {
			return mysql_num_rows($this->query);
		}
	}

	/**
	 * Return number of affected rows in previous sql operation.
	 *
	 * @return number
	 * @access pubilc 
	 */
	public function affected()
	{
		return $this->affectedRows;
	}

	/**
	 * Fetches a row from the result.
	 * 
	 * @return array|boolean
	 * @access public
	 */
	public function fetchRow()
	{
		if($row = mysql_fetch_array($this->query, MYSQL_ASSOC)) {
			return $row;
		} else if ( $this->size() > 0 ) {
			mysql_data_seek($this->query, 0);
			return false;
		} else {
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
		return $this->error;
	}
}

?>
