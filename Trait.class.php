<?php
include_once('ComplexObject.class.php');

class Trait extends ComplexObject{
	static $prefixes_objets=array('pos_debut'=>'Coord', 'liaison'=>'Liaison', 'border'=>'Border');
	static $identifiants=array('id','id2','id3','type');
        static $traitement_special=array('liaison');
	var $id;
	var $id2;
	var $id3;
	var $pos_debut;
	var $width;
	var $height;
	var $liaison;
	var $border;
	var $label;
	var $name;
	var $type;

        static $ajoutes;

        function get($filtres, $str_all) {
            $trait=parent::get($filtres,$str_all);
            if (is_null($trait))
                return null;
            $filtres_liaison=array();
            $liaison=new Liaison();
            foreach($filtres as $nom_filtre=>$valeur_filtre)
                foreach($liaison as $attribut=>$valeur_attribut)
                    if ($nom_filtre==$attribut)
                        $filtres_liaison[$attribut]=$valeur_filtre; // Ajouter tous les filtres du trait qui s'appliquent à la liaison
                    
            if ($str_all=='all') {
                foreach($trait as $index=>$un_trait) {
                    foreach(Liaison::$identifiants as $valeur_attribut)
                        $filtres_liaison[$valeur_attribut]=$un_trait->$valeur_attribut; // Ajouter les caractéristiques du trait courant
                    $trait[$index]->liaison=ComplexObjectToGet('Liaison', $filtres_liaison);
                }
            }
            else
                $trait->liaison=ComplexObjectToGet('Liaison', $filtres_liaison);
            return $trait;
	}

	function add() {
            $this->liaison->addOrUpdate();
            parent::add();
	}

	function update() {
            parent::update();
	}

        function addOrUpdate() {
            $this->liaison->addOrUpdate();
            parent::addOrUpdate();
        }

	function changetoBD() {
		$requete='UPDATE traits SET ';
		$debut=true;
		foreach($this as $id=>$value) {
			$bd_values=$this->attributeToBDValues($id);
			foreach($bd_values as $bd_index=>$bd_val) {
				$requete.=($debut?'':', ').$bd_index.'='.(is_null($bd_val)?$bd_val:'\''.$bd_val.'\'');
				$debut=false;
			}
		}
		$requete.=' WHERE id=\''.$this->id.'\' AND id2=\''.$this->id2.'\' AND id_session='.Personne::$id_session;
		if (is_null($this->id3))
			$requete.=' AND id3=NULL';
		else
			$requete.=' AND id3=\''.$this->id3.'\'';
		Requete::query($requete) or die(mysql_error());
	}
	
	function getConcernes() {
		$concernes=array($this->id,$this->id2);
		if (!is_null($this->id3))
			$concernes[]=$this->id3;
		return $concernes;
	}
	
	static function getTraitsConcernesPar($id1,$id2) {
		$requete='SELECT '.implode(', ',$this->getBDFields()).' '
				.'FROM traits '
				.'WHERE id_session='.Personne::$id_session.' AND (id LIKE \''.$id.'\' OR id2 LIKE \''.$id2.'\' OR id3 IS NULL)';
		$resultat_requete=Requete::query($requete);
		$traits=array();
		while ($infos=mysql_fetch_array($resultat_requete)) {
			$traits[]=new Trait($infos);
		} 
		return $traits;
	}
	
	static function getTraitsCouple($id, $id2, $filtre=array()) {
		$requete='SELECT '.implode(', ',$this->getBDFields()).' '
				.'FROM traits '
				.'WHERE id LIKE \''.$id.'\' AND id2 LIKE \''.$id2.'\' AND id_session='.Personne::$id_session;
		foreach($filtre as $filtre) {
			$requete.=' AND '.$filtre;
		}
		$resultat_requete=Requete::query($requete);
		$traits=array();
		while ($infos=mysql_fetch_array($resultat_requete)) {
			$traits[]=new Trait($infos);
		} 
		return $traits;
	}
	
	static function getEpouxMalPlaces() {
		$requete='SELECT b1.id AS id_1, b2.id AS id_2 '
				.'FROM boites b1, boites b2, traits t'
				.'WHERE b1.id IS t.id AND b2.id IS t.id2 AND t.id3 IS NULL AND b1.id_session IS t.id_session AND b2.id_session IS t.id_session AND t.id_session='.Personne::$id_session.' '
				.'AND ((b1.pos_x < b2.pos_x AND b1.sexe LIKE \'F\') OR (b1.pos_x > b2.pos_x AND b2.sexe LIKE \'M\'))';
		$resultat_requete=Requete::query($requete);
		$couples=array();
		while ($infos=mysql_fetch_array($resultat_requete)) {
			$couples[]=array(Boite::getBoite($infos['b1.id']),Boite::getBoite($infos['b2.id']));
		}
		return $couples;
	}
	
	static function traitEnfantExiste($id1,$id2,$id3) {
		$requete='SELECT id '
				.'FROM traits '
				.'WHERE id_session='.Personne::$id_session.' AND (id LIKE \''.$id1.'\' AND id2 LIKE \''.$id2.'\' AND id3 LIKE \''.$id3.'\')';
		$resultat_requete=Requete::query($requete);
		return mysql_num_rows($resultat_requete)>0;
	}
	
	static function supprimer_sans_issue() {
		$requete='SELECT id, id2, id3, type FROM traits t'
				.'WHERE ((SELECT Count(b.id) FROM boites b WHERE b.id LIKE \'t.id\')=0 OR'
					   .'(SELECT Count(b.id) FROM boites b WHERE b.id LIKE \'t.id2\')=0 OR'
				 	  .'((SELECT Count(b.id) FROM boites b WHERE b.id LIKE \'t.id3\')=0 AND t.id3 IS NOT NULL) AND id_session='.Personne::$id_session.')';
		$resultat_requete=Requete::query($requete);
		while ($infos=mysql_fetch_array($resultat_requete)) {
			$requete_suppr='DELETE FROM traits WHERE id LIKE \''.$infos['id'].'\' AND id2 LIKE \''.$infos['id2'].'\' AND id3 LIKE \''.$infos['id3'].'\' AND id_session='.Personne::$id_session;
			Requete::query($requete_suppr);
		}
	}
	
	static function type_id_to_nom_champ($type_id) {
		return $type_id=='Chef de famille'?'id':'id3';
	}
}
?>