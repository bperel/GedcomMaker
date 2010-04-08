<?php
class Marge extends ComplexObject {
	var $niveau;
	var $marge;
	
	function setMarge($marge) {
		if (is_null($this->get(array('niveau'=>$this->niveau))))
			$requete_set='INSERT INTO marges(niveau,marge, id_session) VALUES ('.$this->niveau.','.$marge.','.Personne::$id_session.')';
		else
			$requete_set='UPDATE marges SET marge='.$marge.' WHERE niveau='.$this->niveau.' AND id_session='.Personne::$id_session;
		Requete::query($requete_set) or die(mysql_error());
	}
	
	function init() {
		if (is_null($this->get(array('niveau'=>$this->niveau))))
			$requete_set='INSERT INTO marges(niveau,marge, id_session) VALUES ('.$this->niveau.',0,'.Personne::$id_session.')';
		Requete::query($requete_set) or die(mysql_error());
		
	}
}

?>