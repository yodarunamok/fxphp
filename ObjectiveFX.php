<?php
/*******************************************************
 * ObjectiveFX.php by Masayuki Nii (msyk@msyk.net)
 * Start from Jan 23, 2011
 * 
 * How to use this, check following blog article:
 * https://server.msyk.net/wiki/pages/f8C0Q7a6/
 * *****************************************************
 */

define('FX_OBJECTIVE','I WILL USE IT!');

class ObjectiveFX	{
	var $result;
	
	function __construct( $fxFullResult )	{
		$this->result = $fxFullResult;
	}
	
	function getRecords()	{
		$recordArray = array();
		foreach($this->result as $index=>$row)	{
			$recordArray[] = new ObjectiveFXRecord($row);
		}
		return $recordArray;
	}
}

class ObjectiveFXRecord	{
	var $recordArray;
	
	function __construct( $row )	{
		foreach($row as $field=>$val)	{
			$fieldName = $field;
			if (strpos($fieldName, '::') != false)	{
				$fieldName = str_replace('::', '__', $fieldName);
			}
			$this->recordArray[$fieldName] = $val;
		}
	}
	
	function __get($name)	{
		if (!isset($this->recordArray[$name]))	{
			throw new ObjectiveFXException("The field name '{$name}' that you specified doesn't exist.");
		}
		if (is_array($this->recordArray[$name]))	{
			$portalArray = array();
			foreach($this->recordArray[$name] as $ix=>$row)	{
				$portalArray[] = new ObjectiveFXRecord($row);
			}
			return $portalArray;
		}
		return $this->recordArray[$name];
	}
}

class ObjectiveFXException extends Exception	{
}

?>