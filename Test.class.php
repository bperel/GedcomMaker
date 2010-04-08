<?php
include_once('ComplexObject.class.php');
class Test extends ComplexObject {
	var $a=1;
	var $b=2;
	function Test() {
		echo '!'.implode(', ',$this->getBDFields());
	}
}

$a=new Test();