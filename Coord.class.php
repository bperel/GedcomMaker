<?php
include_once('ComplexObject.class.php');
class Coord extends ComplexObject{
	var $x;
	var $y;
	
	function incr($x,$y) {
		$this->x+=$x;
		$this->y+=$y;
	}

	function decr($x,$y) {
		$this->x-=$x;
		$this->y-=$y;
	}
}
?>