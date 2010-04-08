<?php
class ComplexObject {
	var $prefixes_objets=array();
	
	function ComplexObject(array $args=array()) {
		foreach($this->prefixes_objets as $variable=>$type) {
			$this->$variable=new $type();
		}
		foreach($args as $index=>$value) {
			$this->setFromBD($index,$value);
		}
	}
	
	function get($filtres=array()) {
		$debug=debug_backtrace();
		$nom_classe=get_class($debug[0]['object']);
		$nom_table=strtolower($nom_classe).'s';
		$requete='SELECT '.implode(', ',ComplexObject::getBDFields()).' '
				.'FROM '.$nom_table.' '
				.'WHERE id_session='.Personne::$id_session;
		echo $requete."\n";
		foreach($filtres as $champ=>$valeur)
			$requete.=' AND '.$champ.' LIKE \''.$valeur.'\'';
		$resultat_requete=Requete::query($requete) or die(mysql_error());
		if ($infos=mysql_fetch_array($resultat_requete))
			return new $nom_classe($infos);
		return null;
	}
	

	function add() {
		$debug=debug_backtrace();
		$nom_classe=get_class($debug[0]['object']);
		$nom_table=strtolower($nom_classe).'s';
		$requete='INSERT INTO '.$nom_table.' ('.implode(', ',ComplexObject::getBDFields()).', id_session) '
				.'VALUES ('.implode(', ',ComplexObject::getFormattedValues()).', '.Personne::$id_session.')';
		Requete::query($requete) or die(mysql_error());
	}
	
	function getBDFields(){
		$fields=array();
		foreach($this as $attr=>$val)
			if ($attr!='prefixes_objets')
				$fields=array_merge($fields,self::attributeToBDFields($attr));
		return $fields;
	}
	
	function getFormattedValues(){
		$fields=array();
		foreach($this as $attr=>$val)
			if ($attr!='prefixes_objets')
				$fields=array_merge($fields,self::attributeToBDValues($attr));
		return $fields;
	}
	
	function attributeToBDFields($index) {
		$pos_underscore=strpos($index,'_');
		$bd_fields=array();
		if (array_key_exists($index,$this->prefixes_objets) || ($pos_underscore!==null && array_key_exists(substr($index,0,$pos_underscore),$this->prefixes_objets))) {
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
		if (array_key_exists($index,$this->prefixes_objets) || ($pos_underscore!==null && array_key_exists(substr($index,0,$pos_underscore),$this->prefixes_objets))) {
			foreach($this->$index as $attr=>$val)
				$bd_values[$index.'_'.$attr]=toNullableString($val);
		}
		else {
			$bd_values[$index]=toNullableString($this->$index);
		}
		return $bd_values;
	}
	
	function setFromBD($index,$value) {
		$pos_underscore=strpos($index,'_');
		if ($pos_underscore===null)
			$this->$index=$value;
		else {
			$prefixe=substr($index,0,$pos_underscore);
			if (array_key_exists($prefixe,$this->prefixes_objets)) {
				$champ=substr($index,strrpos($index,'_')+1,strlen($index)-strrpos($index,'_')-1);
				$this->$prefixe->$champ=$value;
			}
			else
				$this->$index=$value;
		}	
	}
}

function toNullableString($string) {
	if (is_null($string))
		return 'NULL';
	else return '\''.$string.'\'';
}