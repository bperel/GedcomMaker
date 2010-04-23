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
	
	/*
	function get($filtres, $str_all) {
		$boites= parent::get($filtres,$str_all);
		if (!is_null($boites)) {
			if (is_array($boites))
				foreach($boites as $boite)
					$boite->fixNiveauCourant();
			else
				$boites->fixNiveauCourant();
		}
		return $boites;
	}*/
	
	function fixNiveauCourant() {
		Personne::$niveau_courant=intval($this->pos->y / (HAUTEUR_PERSONNE+HAUTEUR_GENERATION));
		//echo 'Boite trouvee en y='.$this->pos->y.', niveau courant fixe a '.Personne::$niveau_courant."\n";
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