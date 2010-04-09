<?php 
include_once('ComplexObject.class.php');
include_once('Coord.class.php');
include_once('Dimension.class.php');

class Boite extends ComplexObject {
	static $prefixes_objets=array('pos'=>'Coord','dimension'=>'Dimension');
	static $identifiants=array('id');
	var $id;
	var $sexe;
	var $recursion;
	var $contenu;
	var $pos;
	var $dimension;
	
	static function changeToBD() {
		$requete='UPDATE boites SET ';
		$debut=true;
		foreach($this as $id=>$value) {
			$bd_values=$this->attributeToBDValues($id);
			foreach($bd_values as $bd_index=>$bd_val) {
				$requete.=($debut?'':', ').$bd_index.'='.(is_null($bd_val)?$bd_val:'\''.$bd_val.'\'');
				$debut=false;
			}
		}
		$requete.=' WHERE id=\''.$this->id.'\' AND id_session='.Personne::$id_session;
		Requete::query($requete) or die(mysql_error());
	}
}