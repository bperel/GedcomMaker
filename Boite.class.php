<?php 
include_once('ComplexObject.class.php');
include_once('Coord.class.php');
include_once('Dimension.class.php');

class Boite extends ComplexObject {
	var $prefixes_objets=array('pos'=>'Coord','dimension'=>'Dimension');
	var $id;
	var $sexe;
	var $recursion;
	var $contenu;
	var $pos;
	var $dimension;

	static function getAll() {
		$requete='SELECT '.implode(', ',$this->getBDFields()).' '
				.'FROM boites '
				.'WHERE id_session='.Personne::$id_session;
		$resultat_requete=Requete::query($requete);
		$boites=array();
		while ($infos=mysql_fetch_array($resultat_requete))
			$boites[]= new Boite($infos);
			
		return $boites;
	}
	
	static function ajouter($args) {
		$requete='INSERT INTO boites('.implode(', ',ComplexObject::getBDFields());
		$debut=true;
		foreach ($args as $index=>$value) {
			if (!$debut)
				$requete.=', ';
			$requete.=$index;
			$debut=false;
		}
		$requete.=', id_session) VALUES (';
		$debut=true;
		foreach ($args as $index=>$value) {
			if (!$debut)
				$requete.=', ';
			$requete.='\''.$value.'\'';
			$debut=false;
		}
		$requete.=', '.Personne::$id_session.')';
		Requete::query($requete) or die(mysql_error());
	}
	
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