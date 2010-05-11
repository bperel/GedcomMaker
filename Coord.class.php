<?php
include_once('ComplexObject.class.php');
class Coord extends ComplexObject{
	var $x;
	var $y;
	
	function incr($x,$y) {
		$this->x+=$x;
		$this->y+=$y;
            return $this;
	}

	function decr($x,$y) {
            $this->x-=$x;
            $this->y-=$y;
            return $this;
	}

        function incr_x($x) {
            $this->x+=$x;
            return $this;
        }
        function decr_x($x) {
            $this->x-=$x;
            return $this;
        }

        function incr_y($y) {
            $this->y+=$y;
            return $this;
        }
        function decr_y($y) {
            $this->y-=$y;
            return $this;
        }

        static function echanger (Coord $c1, Coord $c2) {
            $c_temp=$c1;
            $c1=clone $c2;
            $c2=clone $c_temp;
            return array($c1,$c2);
        }
}
?>