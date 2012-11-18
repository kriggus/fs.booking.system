<?php
namespace FSBookingSys\Utils;

class Utils
{

	private static $instance;
	
	public static function getInstance()
	{
		if ( is_null( self::$instance ) )
		{
			self::$instance = new self();
		}
		return self::$instance;
	}

	//TODO: implement
	private function __construct ()
	{

	}
	
	//TODO: implement
	public function loadSFBookingSystem()
	{
// 		set_include_path('.'
// 		. PATH_SEPARATOR . dirname(dirname(__FILE__))
// 		. PATH_SEPARATOR . get_include_path()
// 		);

	}
	
	//TODO: implement
	public function loadAll()
	{
// 	
// 		$this->loadSFBookingSystem();
	}
	
	//TODO: implement
	public function getHeader()
	{
		
	}
	
	//TODO: implement
	public function getFooter()
	{
	
	}
}

?>