<?php
class Mariage {
	var $id;
	var $conjoint1;
	var $conjoint2;
	var $date_mariage;
	var $lieu_mariage;
	var $enfants;
	
	function Mariage($conjoint1=null,$conjoint2=null,$date_mariage=null,$lieu_mariage=null,$enfants=array()) {
		$this->conjoint1=$conjoint1;
		$this->conjoint2=$conjoint2;
		$this->date_mariage=$date_mariage;$this->lieu_mariage=$lieu_mariage;$this->enfants=$enfants;
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
}?>