<?php
include_once('ComplexObject.class.php');
class Dimension {
	var $width;
	var $height;
	
	function Dimension($width=null, $height=null) {
		$this->width=$width;
		$this->height=$height;
	}
}

?>