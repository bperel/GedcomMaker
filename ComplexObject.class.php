<?php
class ComplexObject {
	static $prefixes_objets=array();
	static $identifiants=array();
	static $traitement_special=array();
	
	function ComplexObject(array $args=array()) {
		if (isset(static::$prefixes_objets)) {
                    foreach(static::$prefixes_objets as $variable=>$type) {
                        $this->$variable=new $type();
                    }
                }
				
		foreach($args as $index=>$value) {
			if ($index!='prefixes_objets' /*&& !in_array($index,static::$traitement_special)*/)
				$this->setFromBD($index,$value);
		}
	}

        function exists($filtres=array()) {
            $nom_table=$this->getNomTable();
            $requete='SELECT EXISTS(SELECT 1 '
                    .'FROM '.$nom_table.' '
                    .'WHERE id_session='.Personne::$id_session;
            foreach($filtres as $champ=>$valeur) {
                if (is_int($champ))
                    $requete.=' AND ('.$valeur;
                else
                    $requete.=' AND ('.$champ.' LIKE \''.$valeur.'\'';
                if (is_null($valeur))
                    $requete.=' OR '.$champ.' IS NULL';
                $requete.=')';
            }
            $requete.=') AS existe';
            $resultat_requete=Requete::query($requete) or die(mysql_error());
            if ($infos=mysql_fetch_array($resultat_requete))
                return $infos['existe']==1;
        }

	function get($filtres=array(),$str_all=false,$special_field=null) {
		$all=$str_all=='all';
                $nom_classe=get_class($this);
                $nom_table=$this->getNomTable();
                $fields=is_null($special_field) ? implode(', ',ComplexObject::getBDFields()) : $special_field;
		$requete='SELECT '.$fields.' '
				.'FROM '.$nom_table.' '
				.'WHERE id_session='.Personne::$id_session;
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
		return count($objets)==0 ? null : ($all ? $objets : $objets[0]);
	}
	

	function add() {
		$nom_table=$this->getNomTable();
		$requete='INSERT INTO '.$nom_table.' ('.implode(', ',ComplexObject::getBDFields()).', id_session) '
			.'VALUES ('.implode(', ',ComplexObject::getFormattedValues()).', '.Personne::$id_session.')';
		Requete::query($requete) or die(mysql_error());
	}
	
	function update() {
		$nom_table=$this->getNomTable();
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
                    if ($valeurs[$identifiant]==='NULL')
                        $requete.=' AND '.$identifiant.' IS NULL';
                    else
			$requete.=' AND '.$identifiant.'='.$valeurs[$identifiant];
		}
		
		Requete::query($requete) or die(mysql_error());
	}
	
	function addOrUpdate() {
		$filter=array();
		foreach(static::$identifiants as $identifiant)
                    $filter[$identifiant]=$this->$identifiant;

		if (ComplexObjectExists(get_class($this),$filter)) {
                    $this->update();
                }
		else {
                    $this->add();
                }
	}

        function __toString() {
            $str=get_class($this).' ';
            foreach(static::$identifiants as $identifiant)
                $str.= $identifiant.'='.$this->$identifiant.' - ';
            $str.= "\n";
            return $str;
        }
	
	function getBDFields(){
		$fields=array();
		foreach($this as $attr=>$val) {
			if ($attr!='prefixes_objets' && !in_array($attr,static::$traitement_special))
				$fields=array_merge($fields,static::attributeToBDFields($attr));
		}
		return $fields;
	}
	
	function getFormattedValues(){
		$fields=array();
		foreach($this as $attr=>$val) {
			if ($attr!='prefixes_objets' && !in_array($attr,static::$traitement_special)) 
				$fields=array_merge($fields,static::attributeToBDValues($attr));
                }
		return $fields;
	}
	
	function attributeToBDFields($index) {
		$pos_underscore=strrpos($index,'_');
		$bd_fields=array();
		if (array_key_exists($index,static::$prefixes_objets)) {
			foreach($this->$index as $attr=>$val)
				$bd_fields[]=$index.'_'.$attr;
		}
		else {
			$bd_fields[0]=$index;
		}
		return $bd_fields;
	}
	
	function attributeToBDValues($index) {
		$bd_values=array();
		if (array_key_exists($index,static::$prefixes_objets)) {
			foreach($this->$index as $attr=>$val) {
				$bd_values[$index.'_'.$attr]=toNullableString($val);
                        }
		}
		else {
			if ((is_null($this->$index) || empty($this->$index)) && in_array($index,static::$identifiants))
				$bd_values[$index]=$this->getNext($index);
			else
				$bd_values[$index]=toNullableString($this->$index);
		}
		return $bd_values;
	}
	
	function getNext ($champ) {
            if ($champ==='id3') return 'NULL';
            $requete='SELECT Max('.$champ.') AS max FROM '.$this->getNomTable().' WHERE id_session='.Personne::$id_session;
		$resultat=Requete::query($requete);
		if ($infos=mysql_fetch_array($resultat)) {
			//echo 'Max '.getNomTable(get_class($this)).' : '.$infos['max'];
			if (is_null($infos['max']))
				return 1;
			elseif (is_int(intval($infos['max'])))
				return intval($infos['max'])+1;
			else
				fatal_error('Erreur : le champ '.$champ.' n\'a pas été renseigné et n\'est pas un entier dans la table '.$this->getNomTable()."\n"
						   .'Requête : '.$requete);
		}
	}
	
	function setFromBD($index,$value) {
            if(is_integer($index))
                return;
            $pos_last_underscore=strrpos($index,'_');
            if (!$pos_last_underscore || !array_key_exists(substr($index,0,$pos_last_underscore), static::$prefixes_objets))
                $this->$index=$value;
            else {
                $prefixe=substr($index,0,$pos_last_underscore);
                $attr=substr($index,$pos_last_underscore+1,strlen($index)-$pos_last_underscore-1);
                $this->$prefixe->$attr=$value;
            //if (!isset($this->$prefixe))
            //    $this->$prefixe=new static::$prefixes_objets[$index];
            } 
	}

        function equals($other, $strict=false) {
            foreach($this as $attr=>$value) {
                if ($strict || in_array($attr,static::$identifiants)) {
                    if (is_object($value)) {
                        if (!($value->equals($other->$attr)))
                            return false;
                    }
                    elseif (!is_object($other)) {
                        $a=1;
                    }
                    elseif ($value!==$other->$attr)
                        return false;
                }
            }
            return true;
        }

        function equalsValue($other) {
            return $this->equals($other,true);
        }

    function getField($champ) {
        if (!is_string($champ)) {
            $a=1;
        }
        $pos=(strpos($champ,'->'));
        $objet=clone $this;
        while ($pos!==false) {
            $nom_sous_objet=substr($champ,0,$pos);
            $champ=substr($champ,$pos+2,strlen($champ)-$pos-2);
            if (is_null($this->$nom_sous_objet))
                fatal_error('Unknown property '.$nom_sous_objet);
            $objet=$objet->$nom_sous_objet;
            $pos=(strpos($champ,'->'));
        }
        if (is_null($objet->$champ))
            fatal_error('Can\'t get field '.$champ);
        return $objet->$champ;
    }

    function getNomTable() {
        $nom_classe=get_class($this);
        $nom_classe[0]=strtolower($nom_classe[0]);
        return strtolower(str_replace('_','s_',preg_replace('#([A-Z])#', '_$1', $nom_classe)).'s');
    }

    function ajouter_a_retour() {
        Personne::$retour[$this->getNomTable()][]=$this;
        echo $this."Creation\n";
        foreach(debug_backtrace() as $i=>$ligne_debug)
            echo '['.$i.'] => '.$ligne_debug['file'].', line '.$ligne_debug['line']."\n";
        echo "\n";
    }

    function modifier_dans_retour($indice) {
        if ($this->equalsValue(Personne::$retour[$this->getNomTable()][$indice])) {
            echo $this."Aucun changement\n";
            return;
        }
        Personne::$retour[$this->getNomTable()][$indice] = $this;
        echo $this."Modification\n";
        foreach(debug_backtrace() as $i=>$ligne_debug)
            echo '['.$i.'] => '.$ligne_debug['file'].', line '.$ligne_debug['line']."\n";
        echo "\n";
    }

    function mettre_dans_retour() {
        if (!in_array(get_class($this),array('Trait','Boite')))
            fatal_error('Impossible de mettre autre chose qu\'une boite ou un trait');
        $indice=$this->estDansRetour();
        if (!is_null($indice)) {
            $this->modifier_dans_retour($indice);
        }
        else {
            $this->ajouter_a_retour();
        }
    }

    function estDansRetour() {
        $a=Personne::$retour;
        foreach(Personne::$retour[$this->getNomTable()] as $i=>$element) {
            if ($this->equals($element))
                return $i;
        }
        return null;
    }
}

function toNullableString($string) {
	if (is_null($string))
		return 'NULL';
	else {
            if (is_object($string)) {
                $a=1;
            }
            return '\''.str_replace("'","",$string).'\'';
        }
}

function ComplexObjectToGet($type, $filtres=array(),$str_all=false) {
	$complexObject=new $type();
	return $complexObject->get($filtres,$str_all);
}

function ComplexObjectExists($type,$filtres=array()) {
    $complexObject=new $type();
    return $complexObject->exists($filtres);
}
/**
 * Retourne une seule valeur, et non un tableau comme ComplexObjectToGet
 */
function ComplexObjectFieldToGet($type,$champ,$filtres=array(),$no_error=false) {
	$o=ComplexObjectToGet($type,$filtres,'all');
	if (is_null($o)) {
            if ($no_error)
                return null;
            else
                fatal_error('Can\'t get object '.$type.' ('.implode(',',$filtres).')');
        }
        if (!is_array($o))
            $o=array($o);
        $liste_champs=array();
        foreach($o as $objet) {
            $valeur_champ=null;
            $liste_champs[]=$objet->getField($champ);
        }
	return count($liste_champs)==1 ? $liste_champs[0] : $liste_champs;
}

function ComplexObjectMinToGet($type,$champ,$filtres=array()) {
    $complexObject=new $type();
    return $complexObject->get($filtres,'all','MIN('.$champ.') AS min');
}

function trier(&$tab, $critere, $ordre='asc') {
    global $cr;$cr=$critere;
    global $or;$or=$ordre;
    if (!is_array($tab)){
        $a=1;
    }
    usort($tab,'tri');
}

function tri($a,$b) {
    global $cr;
    global $or;
    if ($a->getField($cr) === $b->getField($cr))
        return 0;
    return (($a->getField($cr) > $b->getField($cr) && $or=='asc')
          ||($a->getField($cr) < $b->getField($cr) && $or=='desc')) ? 1 : -1;
}

function fatal_error($str) {
    echo $str;
    print_r(debug_print_backtrace());
    exit(0);
}