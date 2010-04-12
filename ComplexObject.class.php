<?php
class ComplexObject {
	static $prefixes_objets=array();
	static $identifiants=array();
	
	function ComplexObject(array $args=array()) {
		if (isset(static::$prefixes_objets))
			foreach(static::$prefixes_objets as $variable=>$type)
				$this->$variable=new $type();
				
		foreach($args as $index=>$value) {
			if ($index!='prefixes_objets')
				$this->setFromBD($index,$value);
		}
	}
	
	function get($filtres=array(),$str_all=false) {
		$all=$str_all=='all';
		$nom_classe=get_class($this);
		$nom_table=getNomTable($nom_classe);
		$requete='SELECT '.implode(', ',ComplexObject::getBDFields()).' '
				.'FROM '.$nom_table.' '
				.'WHERE id_session='.Personne::$id_session;
		echo $requete."\n";
		foreach($filtres as $champ=>$valeur) {
			if (is_int($champ))
				$requete.=' AND '.$valeur;
			else
				$requete.=' AND '.$champ.' LIKE \''.$valeur.'\'';
		}
		$resultat_requete=Requete::query($requete) or die(mysql_error());
		$objets=array();
		while ($infos=mysql_fetch_array($resultat_requete)) {
			$infos2=array();
			foreach($infos as $champ=>$valeur)
				if (!is_integer($champ))
					$infos2[$champ]=$valeur;
			$objets[]=new $nom_classe($infos2);
		}
		return $objets==array() ? null : ($all ? $objets : $objets[0]);
	}
	

	function add() {
		$nom_classe=get_class($this);
		$nom_table=getNomTable($nom_classe);
		$requete='INSERT INTO '.$nom_table.' ('.implode(', ',ComplexObject::getBDFields()).', id_session) '
				.'VALUES ('.implode(', ',ComplexObject::getFormattedValues()).', '.Personne::$id_session.')';
		Requete::query($requete) or die(mysql_error());
	}
	
	function update() {
		$nom_classe=get_class($this);
		$nom_table=getNomTable($nom_classe);
		$champs=ComplexObject::getBDFields();
		$valeurs=ComplexObject::getFormattedValues();
		
		$requete='UPDATE '.$nom_table.' SET';
		foreach($champs as $id_champ=>$champ) {
			if ($id_champ!=0)
				$requete.=',';
			$requete.=' '.$champ.'='.$valeurs[$champ];
		}
		$requete.=' WHERE id_session='.Personne::$id_session;
		foreach(static::$identifiants as $identifiant) {
			$requete.=' AND '.$identifiant.'='.$valeurs[$identifiant];
		}
		
		Requete::query($requete) or die(mysql_error());
	}
	
	function addOrUpdate() {
		$filter=array();
		foreach(static::$identifiants as $identifiant)
			$filter[$identifiant]=$this->$identifiant;
		if (is_null(ComplexObjectToGet(get_class($this),$filter)))
			$this->add();
		else
			$this->update();
	}
	
	function getBDFields(){
		$fields=array();
		foreach($this as $attr=>$val) {
			if ($attr!='prefixes_objets')
				$fields=array_merge($fields,static::attributeToBDFields($attr));
		}
		return $fields;
	}
	
	function getFormattedValues(){
		$fields=array();
		foreach($this as $attr=>$val)
			if ($attr!='prefixes_objets')
				$fields=array_merge($fields,static::attributeToBDValues($attr));
		return $fields;
	}
	
	function attributeToBDFields($index) {
		$pos_underscore=strpos($index,'_');
		$bd_fields=array();
		if (array_key_exists($index,static::$prefixes_objets) || ($pos_underscore!==null && array_key_exists(substr($index,0,$pos_underscore),static::$prefixes_objets))) {
			foreach($this->$index as $attr=>$val)
				$bd_fields[]=$index.'_'.$attr;
		}
		else {
			$bd_fields[0]=$index;
		}
		return $bd_fields;
	}
	
	function attributeToBDValues($index) {
		$pos_underscore=strpos($index,'_');
		$bd_values=array();
		if (array_key_exists($index,static::$prefixes_objets) || ($pos_underscore!==null && array_key_exists(substr($index,0,$pos_underscore),static::$prefixes_objets))) {
			foreach($this->$index as $attr=>$val)
				$bd_values[$index.'_'.$attr]=toNullableString($val);
		}
		else {
			if ((is_null($this->$index) || $this->$index==='') && in_array($index,static::$identifiants))
				$bd_values[$index]=$this->getNext($index);
			else
				$bd_values[$index]=toNullableString($this->$index);
		}
		return $bd_values;
	}
	
	function getNext ($champ) {
		$requete='SELECT Max('.$champ.') AS max FROM '.getNomTable(get_class($this)).' WHERE id_session='.Personne::$id_session;
		echo $requete;
		$resultat=Requete::query($requete);
		if ($infos=mysql_fetch_array($resultat)) {
			echo 'Max '.getNomTable(get_class($this)).' : '.$infos['max'];
			if (is_null($infos['max']))
				return 1;
			elseif (is_int(intval($infos['max'])))
				return intval($infos['max'])+1;
			else {
				echo 'Erreur : le champ '.$champ.' n\'a pas �t� renseign� et n\'est pas un entier dans la table '.getNomTable(get_class($this))."\n";
				echo 'Requ�te : '.$requete;
				print_r(debug_backtrace());
				exit(0);
			}
		}
	}
	
	function setFromBD($index,$value) {
		$pos_underscore=strpos($index,'_');
		if ($pos_underscore===null)
			$this->$index=$value;
		else {
			$prefixe=substr($index,0,$pos_underscore);
			if (isset(static::$prefixes_objets) && array_key_exists($prefixe,static::$prefixes_objets)) {
				$champ=substr($index,strrpos($index,'_')+1,strlen($index)-strrpos($index,'_')-1);
				$this->$prefixe->$champ=$value;
			}
			else {
				$this->$index=$value;
			}
		}	
	}
}

function toNullableString($string) {
	if (is_null($string))
		return 'NULL';
	else return '\''.str_replace("'","",$string).'\'';
}
	
function getNomTable($nom_classe) {
	$nom_classe[0]=strtolower($nom_classe[0]);
	return strtolower(str_replace('_','s_',preg_replace('#([A-Z])#', '_$1', $nom_classe)).'s');
}
function ComplexObjectToGet($type, $filtres=array(),$str_all=false) {
	$complexObject=new $type();
	return $complexObject->get($filtres,$str_all);
}