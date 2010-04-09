<?php
class PositionLiaison extends ComplexObject{
	var $prefixes_objets=array('pos'=>'Coord');
	var $id1;
	var $id2;
	var $pos;
	static $identifiants=array('id1','id2');
	
	function addToBD() {
		$requete='INSERT INTO positions_liaisons('.implode(', ',$this->getBDFields()).') VALUES (';
		$debut=true;
		foreach($this as $id=>$value) {
			$bd_values=$this->attributeToBDValues($id);
			foreach($bd_values as $bd_index=>$bd_val) {
				$requete.=($debut?'':', ').(is_null($bd_val)?$bd_val:'\''.$bd_val.'\'');
				$debut=false;
			}
		}
		$requete.=')';
		Requete::query($requete) or die(mysql_error());
	}
	
	static function get($conjoint1, $conjoint2) {
		$requete='SELECT id1, id2, pos_x, pos_y FROM positions_liaisons WHERE id1 LIKE \''.$this->id1.'\' AND id2 LIKE \''.$this->id2.'\'';
		$resultat_requete=Requete::query($requete);
		if ($infos=mysql_fetch_array($resultat_requete))
			return new PositionLiaison($infos);
	}
}

?>