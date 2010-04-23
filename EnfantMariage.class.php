<?php
include_once('ComplexObject.class.php');

class EnfantMariage extends ComplexObject {
	var $id_mariage;
	var $id_enfant;
	static $identifiants=array('id_mariage','id_enfant');
	
	function getNumeroEnfantFratrie ($enfants, $id_enfant) {
		foreach ($enfants as $num_enfant=>$enfant_mariage) {
			if ($num_enfant==$enfant_mariage->id_enfant)
				return $num_enfant;
		}
		return null;
	}
}

?>