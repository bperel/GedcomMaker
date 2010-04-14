<?php
include_once('ComplexObject.class.php');
class Mariage extends ComplexObject{
	var $id;
	var $conjoint1;
	var $conjoint2;
	var $date_mariage;
	var $lieu_mariage;
	var $enfants=array();
	static $identifiants=array('id');
	static $traitement_special=array('enfants');
	
	function get($filtres, $str_all) {
		$this->enfants=$this->getEnfants();
		if (is_null($this->enfants))
			$this->enfants=array();
		parent::get($filtres,$str_all);
	}
	
	function add() {
		$this->addEnfants($this->enfants);
		parent::add();
	}
	
	function update() {
		parent::update();
	}
	
	function getEnfants() {
		if (is_null($this->id))
			$this->id=$this->getNext('id');
		return EnfantMariage::get(array('id'=>$this->id),'all');
	}
	
	function addEnfants(array $enfants) {
		foreach($enfants as $id_enfant) {
			$enfantmariage=new EnfantMariage(array('id_enfant'=>$id_enfant,'id_mariage'=>$this->id));
			$enfantmariage->add();
		}
	}
	
	function detecter_non_references() {
		$texte='';
		$personnes_a_scanner=array($this->conjoint1,$this->conjoint2);
		$personnes_non_referencees=array();
		foreach ($this->enfants as $enfant) {
			array_push($personnes_a_scanner,$enfant);
		}
		foreach ($personnes_a_scanner as $id_personne) {
			if (false===array_search($id_personne,Personne::$personnes_ecrites)
			&&  false===array_search($id_personne,Personne::$personnes_en_cours)) {
				$personne=Personne::$liste[$id_personne];
				array_push($personnes_non_referencees,array('numero'=>$personne->get_numero_personne(),
														   'numero_famille_origine'=>$personne->get_numero_famille_origine(),
														   'numeros_familles_souches'=>$personne->get_numeros_familles_souches()));
			}
		}
		return $personnes_non_referencees;
	}
	
	static function getMariageConcerne ($liste_mariages, $id_enfant) {
		foreach($liste_mariages as $num_mariage=>$mariage) {
			$enfants_mariage=EnfantMariage::get(array('id_mariage'=>$num_mariage),'all');
			foreach($enfants_mariage as $num_enfant=>$enfant)
				if ($id_enfant==$num_enfant)
					return $num_mariage;
		}
		return null;
	}
}?>