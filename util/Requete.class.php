<?php 
class Requete {
	
	static function query($str='') {
		$resultat=@mysql_query(stripslashes($str));
		if (!$resultat) {
			echo "\n\nErreur MySQL pour : \n$str\n\n";
			print_r(debug_backtrace());
			die(mysql_error());
		}
		return $resultat;
	}

}

?>