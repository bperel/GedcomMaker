<?php
//$_POST=array('autres_args'=>urlencode('pz=louis+raymond+henry;nz=chevallereau;ocz=0;p=francoise;n=le+tallec'),'id_session'=>1270639965,'make_tree'=>true,'pseudo'=>'astrofifi','serveur'=>'gw0');
$server='localhost';
$user='root';
$database='gedcommaker';
$password='';
mysql_connect($server, $user, $password);
mysql_select_db($database);
date_default_timezone_set('UTC');
include_once('Util/Requete.class.php');

include_once('Coord.class.php');
include_once('Border.class.php');
include_once('Dimension.class.php');

include_once('Boite.class.php');
include_once('Trait.class.php');
include_once('Marge.class.php');

include_once('Mariage.class.php');

define('DEBUG',isset($_GET['debug']));
define('LARGEUR_PERSONNE',150);
define('HAUTEUR_PERSONNE',50);
define('HAUTEUR_GENERATION',250);
define('ESPACEMENT_MARIAGES',4);
define('ESPACEMENT_ENFANT',40);
define('ESPACEMENT_INCONNUS',60);
define('ESPACEMENT_EPOUX',50);
define('LARGEUR_BORDURE',1);

define('LIMITE_PROFONDEUR_SNIFFER',99);
define('LIMITE_PROFONDEUR',LIMITE_PROFONDEUR_SNIFFER-2);

ini_set ('max_execution_time', 0); 

function PersonneFromBD($id){
	$p=new Personne($id);
	if (is_null($p->from_bd())) {
		return null;
	}
	return $p;
}

Personne::$note_supplementaire='Source : Arbre généalogique Geneanet de Jean-Marcel Boidier';

Personne::$regex_etat_civil='#<td class="highlight2">&nbsp; .tat civil</td>[^<]*</tr></table>[^<]*<ul>[^<]*'
				 .'((?:<li>[^<]*</li>[^<]*)+)</ul>#isu';
Personne::$regex_parents='#<td class="highlight2">&nbsp; Parents</td>[^<]*</tr></table>[^<]*<ul>[^<]*'
					    .Personne::$ligne_personne_classique.'(?:(?:(?!</li>).)*)</li>[^<]*'
					    .Personne::$ligne_personne_classique.'#isu';
Personne::$regex_mariages='#<td class="highlight2">&nbsp; Mariage(?:\()?s?(?:\))? (?:et enfant(?:\()?s?(?:\))?)?(?:<span[^>]+>[^>]*>)*</td>[^<]*</tr></table>(?:[^<]*</h3>)?[^<]*(<ul>[^<]*'
					     .'(?:<li style="vertical\-align: middle;list\-style\-type: (?:circle|disc|square)">Mari.e? ?(?:<em>[^<]+</em>)?[^a]*avec <a href="(?:[^"]+)">(?:[^<]+)</a>(?: <em><bdo dir="ltr">[^<]*</bdo></em>)?'
					     .'(?:(?:(?!, dont).)*, dont[^<]*<ul>[^<]*(?:'.Personne::$ligne_personne_classique2.'(?:(?:(?!</li>).)*)</li>[^<]*)+</ul>)*[^<]*</li>[^<]*)+</ul>)#isu';
Personne::$regex_mariages_conjoints='#<li style="vertical\-align: middle;list\-style\-type: (circle|disc|square)">Mari.e? ?((?:<em>[^<]+</em>)?)[^a]*avec <a href="([^"]+)">([^<]+)</a>(?: <em><bdo dir="ltr">[^<]*</bdo></em>)?'
			   					   .'((?:(?:(?!, dont).)*, dont[^<]*<ul>[^<]*(?:'.Personne::$ligne_personne_classique2.'(?:(?:(?!</li>).)*)</li>[^<]*)+</ul>)?)[^<]*</li>#isu';
Personne::$regex_mariages_enfants='#'.Personne::$ligne_personne_classique.'#isu';
Personne::$regex_patronyme='#<img src="http://images.geneanet\.org/v3/pictos_geneweb/[^/]+/(?:(?:saisie-(?:homme|femme))|sexeinconnu)\.gif" alt="(H|F|\?)" title="(?:H|F|\?)" />'
						  .'</td>[^<]*<td class="highlight2">&nbsp;(?:(?:[^<]*<a href="[^"]*">([^<]+)</a>[^<]*<a href="[^"]*">([^<]+)</a>)|..([^<]*)</td>)#isu';

class Personne {
	// Construction graphique de l'arbre
	static $liste_pos=array();
	static $liste_mariages_dessines=array();
	static $liste_pos_liaisons=array();
	static $liste_boites=array();
	static $liste_personnes_decalees=array();
	static $liste_marges_gauches=array();
	static $niveau_courant=0;
	
	static $id_session=1270639965;
	static $liste_familles=array();
	static $ids_parcourus=array();
	static $ids_en_cours=array();
	static $personnes_ecrites=array();
	static $personnes_en_cours=array();
	static $ligne_personne_classique='<li style="vertical\-align: middle;list\-style\-type: (circle|disc|square);?">(?:<img[^>]*> )?<a href="([^"]+)">([^<]+)</a>';
	static $ligne_personne_classique2='<li style="vertical\-align: middle;list\-style\-type: (?:circle|disc|square);?">(?:<img[^>]*> )?<a href="[^"]+">[^<]+</a>';
	static $regex_titre='#<title>([^<]+)</title>#isu';
	static $regex_etat_civil;
	static $regex_etat_civil_naissance='#^N.e?([^<]*)#isu';
	static $regex_etat_civil_deces='#^D.c.d.e?([^<]*)#isu';
	static $regex_etat_civil_autres='#<li>([^<]*)</li>#isu';
	static $regex_parents;
	static $regex_mariages;
	static $regex_mariages_conjoints;
	static $regex_mariages_conjoints_details='#<em>((?:le[^,]+, )?)((?:[^<]+)?)</em>#isu';
	static $regex_mariages_enfants;
	static $regex_patronyme;
	static $nom_domaine; 
	static $mois=array('janvier'=>'JAN','février'=>'FEB','mars'=>'MAR','avril'=>'AVR','mai'=>'MAY','juin'=>'JUN',
					   'juillet'=>'JUL', 'août'=>'AUG', 'septembre'=>'SEP', 'octobre'=>'OCT', 'novembre'=>'NOV', 'décembre'=>'DEC');
	static $note_supplementaire;
	var $pos;
	var $id;
	var $url;
	var $naissance;
	var $date_naissance=false;
	var $lieu_naissance=false;
	var $date_mort=false;
	var $lieu_mort=false;
	var $mort;
	var $autres;
	var $prenom='';
	var $nom='';
	var $sexe='M';
	var $pere=null;
	var $mere=null;
	var $mariages=null;
	
	static function setIdSession($id_session) {
		self::$id_session=$id_session;
	}
	
	static function getIdSession() {
		return self::$id_session;
	}
	
	function Personne($url,$naissance='',$mort='',$autres='',$prenom='',$nom='',$pere='',$mere='') {
		$this->url=$url;$this->naissance=$naissance;$this->mort=$mort;$this->autres=$autres;
		$this->prenom=$prenom;$this->nom=$nom;
		$this->pere=$pere;
		$this->mere=$mere;
		$this->pos=new Coord(-1,-1);
		$this->id=$this->to_id();
	}
	
	function analyser($from_bd=false) {
		$retour=array();
		if (LIMITE_PROFONDEUR_SNIFFER!=0 && count(debug_backtrace())>LIMITE_PROFONDEUR_SNIFFER) {
			echo '[TMR]<br />';
			return;
		}
		$this->pos=new Coord(0,0);
		if ($from_bd) {
			$boite_vide=new Boite();
			if (is_null($boite_vide->get(array('id'=>$this->id)))) {
				$this->from_bd();
				$champs_utf8=array('prenom','nom','autres','naissance','mort','lieu_naissance','lieu_mort');
				foreach($champs_utf8 as $champ)
					$this->$champ=utf8_decode($this->$champ);
				$marge=new Marge(array('niveau'=>Personne::$niveau_courant));
				$marge->setMarge(0);
				$this->dessiner();
				$retour['boites']['creees'][]=$boite_vide->get(array('id'=>$this->id));
			}
			$pere=PersonneFromBD($this->pere);
			$action='already_done';
			if (!is_null($pere) && $pere->getEtat()!='make_tree') {
				$action='todo';
			}
			$retour['pere']=array('id'=>$this->pere,'action'=>$action);
			$mere=PersonneFromBD($this->mere);
			$action='already_done';
			if (!is_null($mere) && $mere->getEtat()!='make_tree') {
				$action='todo';
			}
			$retour['mere']=array('id'=>$this->mere,'action'=>$action);
			
			$this->setEtat('make_tree');
			return $retour;
		}
		$ch = curl_init($this->url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
		$page = curl_exec($ch);
		curl_close($ch);
		
		preg_match(Personne::$regex_titre,$page,$r_titre);
		
		preg_match(Personne::$regex_etat_civil,$page,$r_etat_civil);
		$naissance='';
		$mort='';
		$autres='';
		if (isset($r_etat_civil[1])) {
			preg_match_all(Personne::$regex_etat_civil_autres,$r_etat_civil[1],$r_etat_civil_infos);
			foreach($r_etat_civil_infos[1] as $info) {
				$info_naissance=preg_match(Personne::$regex_etat_civil_naissance,$info,$r_naissance);
				if ($info_naissance!=0) {
					$naissance=$r_naissance[1];
					list($this->date_naissance,$this->lieu_naissance)=decomposer_naissance_mort($naissance);
				}
				$info_deces=preg_match(Personne::$regex_etat_civil_deces,$info,$r_deces);
				if ($info_deces!=0) {
					$mort=$r_deces[1];
					list($this->date_mort,$this->lieu_mort)=decomposer_naissance_mort($mort);
				}
				if ($info_naissance==0 && $info_deces==0) {
					if (!empty($autres)) $info.='. ';
					$autres.=$info;
				}
			}
		}
		
		$parents=preg_match(Personne::$regex_parents,$page,$r_parents);
		preg_match(Personne::$regex_patronyme,$page,$r_patronyme);
		if (!isset($r_patronyme[0])) {
			echo 'Prenom/Nom pas trouv&eacute; pour '.$this->to_id().'<br />';
		}
		else {
			$sexe=$r_patronyme[1];
			$prenom=$nom='';
			if (empty($r_patronyme[2])) {
				$prenom_nom=$r_patronyme[4];
				$prenom_nom_exploded=explode(' ',$prenom_nom);
				foreach($prenom_nom_exploded as $mot) {
					$regex_classification='#[0-9]*°?#is';
					$comporte_classification=preg_match($regex_classification,$mot,$resultat_classification)!=0;
					if ($mot==mb_strtoupper($mot,'UTF-8') && !(empty($nom) && $comporte_classification)) {
						if (!empty($nom)) $nom.=' ';
						$nom.=$mot;
					}
					else {
						if (!empty($prenom)) $prenom.=' ';
						$prenom.=$mot;
					}
				}
				$nom=mb_strtolower($nom,'UTF-8');
			}
			else {
				$prenom=$r_patronyme[2];
				$nom=mb_strtolower($r_patronyme[3],'UTF-8');
				$nom_exploded=explode(' ',$nom);
				for ($i=0;$i<count($nom_exploded);$i++)
					if (!empty($nom_exploded[$i]))
						$nom_exploded[$i][0]=mb_strtoupper($nom_exploded[$i][0],'UTF-8');
				$nom=implode(' ',$nom_exploded);
			}
			$this->prenom=$prenom;
			$this->nom=$nom;
		}
	
		if (!isset($sexe) ||$sexe=='H')
			$this->sexe='M';
		else
			$this->sexe=$sexe;
		$this->naissance=$naissance;
		$this->mort=$mort;
		$this->autres=$autres;
		$mariages=array();
		preg_match(Personne::$regex_mariages,$page,$r_mariages);
		if (isset($r_mariages[1])) {
			preg_match_all(Personne::$regex_mariages_conjoints,$r_mariages[1],$r_mariages_conjoints);
			
			for($i=0;$i<count($r_mariages_conjoints[0]);$i++) {
				$conjoint_a_parents=$r_mariages_conjoints[1][$i]=='disc';
				$prenom_conjoint='';$nom_conjoint='';
				$url_conjoint=Personne::$nom_domaine.$r_mariages_conjoints[3][$i];
				if (!empty($r_mariages_conjoints[2][$i])) { // Détails mariage
					preg_match(Personne::$regex_mariages_conjoints_details,$r_mariages_conjoints[2][$i],$r_detail_mariage);
					list($date_mariage,$lieu_mariage)=decomposer_naissance_mort($r_detail_mariage[1].$r_detail_mariage[2]);
				}
				else {$date_mariage=$lieu_mariage=''; }
				$enfants=array();
				if (!empty($r_mariages_conjoints[5][$i])) { // Enfants
					preg_match_all(Personne::$regex_mariages_enfants,$r_mariages_conjoints[5][$i],$r_enfants);
					for($j=0;$j<count($r_enfants[0]);$j++) {
						$url_pere=$sexe=='H'?$this->url:Personne::$nom_domaine.$r_mariages_conjoints[3][$i];
						$url_mere=$sexe=='H'?Personne::$nom_domaine.$r_mariages_conjoints[3][$i]:$this->url;
						$url_enfant=Personne::$nom_domaine.$r_enfants[2][$j];
						$id_enfant=Personne::url_to_id($url_enfant);
						array_push($enfants,$id_enfant);
					}
				}
				$id_conjoint=Personne::url_to_id($url_conjoint);
				$mariage=new Mariage($this->id,$id_conjoint,$date_mariage,$lieu_mariage,$enfants);
				array_push($mariages,$mariage);
				$famille_existante=false;
				foreach(Personne::$liste_familles as $famille) {
					if ($famille_existante) break;
					if (($famille->conjoint1==$this->id && $famille->conjoint2==$url_conjoint)
					  ||($famille->conjoint1==$url_conjoint && $famille->conjoint2==$this->id))
						$famille_existante=true;
				}
				if (!$famille_existante)
					array_push(Personne::$liste_familles,$mariage);						
			}
		}
		
		if ($parents==1) {
			$pere_a_parents=$r_parents[1]!='circle';
			$mere_a_parents=$r_parents[4]!='circle';
			
			$pere=new Personne(Personne::$nom_domaine.$r_parents[2],'','','','','',$pere_a_parents?null:false,$pere_a_parents?null:false);
			$id_pere=$pere->to_id();
			$this->pere=$id_pere;
			
			$mere=new Personne(Personne::$nom_domaine.$r_parents[5],'','','','','',$mere_a_parents?null:false,$mere_a_parents?null:false);
			$id_mere=$mere->to_id();
			$this->mere=$id_mere;
		}
		
		$this->mariages=$mariages;
		if ($parents==1 && $pere!=null) {
			if ($pere->verifier_peut_parcourir()) {
				$retour['pere']=array('id'=>$this->pere,'action'=>'todo');
			}
			else
				$retour['pere']=array('id'=>$this->pere,'action'=>'already_done');
		}
		
		if ($parents==1 && $mere!=null) {
			if ($mere->verifier_peut_parcourir()) {
				$retour['mere']=array('id'=>$this->mere,'action'=>'todo');
			}
			else
				$retour['mere']=array('id'=>$this->mere,'action'=>'already_done');
		}
		
		$fin_enfants_precedents=0;
			foreach($this->mariages as $i=>$mariage) {
				$conjoint=PersonneFromBD($mariage->conjoint1==$this->id?$mariage->conjoint2:$mariage->conjoint1);
				$action='already_done';
				if (!is_null($conjoint) && $conjoint->getEtat()!='make_tree') {
					$action='already_done';
					foreach($mariage->enfants as $id_enfant) {
						$action='todo';
						$enfant=PersonneFromBD($id_enfant);
						if (!is_null($enfant) && $enfant->getEtat()!='make_tree') {
							$action='already_done';
						}
						$retour['mariages'][$i]['enfants'][]=array('id'=>$id_enfant,'action'=>$action);
					}
					$action='todo';
					$retour['mariages'][$i]['conjoint']=array('id'=>$conjoint->id,'action'=>$action);
				}
			}
		$this->to_bd();
		$marge=new Marge(array('niveau'=>Personne::$niveau_courant));
		//$marge->init();
		$this->dessiner();
		$boite_vide=new Boite();
		$retour['boites']['creees'][]=$boite_vide->get(array('id'=>$this->id));
		return $retour;
		
	}
	
	function change_bd($changes) {
		$nb_changements=0;
		foreach($changes as $id=>$value) {
			if (!in_array($id,array('pos')))
				$nb_changements++;
			else {
				switch($id) {
					case 'pos':case 'pos_x':case 'pos_y':
						$requete_position_existe='SELECT x,y FROM positions WHERE id LIKE \''.$this->id.'\' AND id_session='.Personne::$id_session;
						$resultat_position_existe=Requete::query($requete_position_existe);
						$position_existe=mysql_num_rows($resultat_position_existe)!=0;
						
					case 'pos':
						if ($position_existe) {
							$requete_change_pos='UPDATE positions SET x='.$value->x.', y='.$value->y.' WHERE id LIKE \''.$this->id.'\' AND id_session='.Personne::$id_session;
							Requete::query($requete_change_pos);
						}
						else {
							$requete_ajout_pos=' INSERT INTO positions(id,id_session,x,y) VALUES (\''.$this->id.'\', '.Personne::$id_session.','.$value->x.','.$value->y.')';
							Requete::query($requete_ajout_pos);
						}
						$this->pos=new Coord($value->x,$value->y);
					break;
					case 'pos_y':
						if ($position_existe) {
							$requete_change_pos='UPDATE positions SET y='.$value.' WHERE id LIKE \''.$this->id.'\' AND id_session='.Personne::$id_session;
							Requete::query($requete_change_pos);
						}
						else {
							$requete_ajout_pos=' INSERT INTO positions(id,id_session,x,y) VALUES (\''.$this->id.'\', '.Personne::$id_session.',NULL,'.$value->y.')';
							Requete::query($position_existe);
						}
						$this->pos->y=$value;
					break;
					
					case 'pos_x':
						if ($position_existe) {
							$requete_change_pos='UPDATE positions SET x='.$value->x.' WHERE id LIKE \''.$this->id.'\' AND id_session='.Personne::$id_session;
							Requete::query($requete_change_pos);
						}
						else {
							$requete_ajout_pos=' INSERT INTO positions(id,id_session,x,y) VALUES (\''.$this->id.'\', '.Personne::$id_session.','.$value->x.',NULL)';
							Requete::query($position_existe);
						}
						$this->pos->x=$value;
					break;
				}
			}
		}
		if ($nb_changements>0) {
			$requete_update='UPDATE personnes ';
			foreach($changes as $id=>$value)
				$requete_update.='SET '.$id.'=\''.$value.'\', ';
			$requete_update.='id=id WHERE id_session='.Personne::$id_session.' AND id LIKE \''.$this->id.'\'';
			Requete::query($requete_update);
		}
	}
	
	function to_bd() {
		$champs_a_transcrire=array('id','naissance','date_naissance','lieu_naissance','date_mort','lieu_mort','mort','autres','prenom','nom','sexe','pere','mere');
		
		$requete='INSERT INTO personnes (`id_session`, `id_pos`';
		foreach($champs_a_transcrire as $champ)
			$requete.=', `'.$champ.'`';
		$requete.=',`etat`) ';
		$requete.='VALUES ('.Personne::$id_session.', -1';
		foreach($champs_a_transcrire as $champ) {
			if (!is_null($this->$champ) && !empty($this->$champ))
				$requete.=', \''.addslashes($this->$champ).'\'';
			else	
				$requete.=', NULL';
		}
		$requete.=', \'En cours\');';
		Requete::query($requete) or die (mysql_error()); 
		$this->change_bd(array('pos'=>$this->pos));
		$champs_mariage_a_transcrire=array('conjoint1','conjoint2','date_mariage','lieu_mariage');
		foreach($this->mariages as $mariage) {
			$requete_mariage='INSERT INTO mariages (id_session';
			foreach ($champs_mariage_a_transcrire as $champ)
				$requete_mariage.=', `'.$champ.'`';
			$requete_mariage.=') VALUES ('.Personne::$id_session;
			foreach($champs_mariage_a_transcrire as $champ) {
				if (!is_null($mariage->$champ) && !empty($mariage->$champ))
					$requete_mariage.=', \''.addslashes($mariage->$champ).'\'';
				else	
					$requete_mariage.=', NULL';
			}
			$requete_mariage.=');';
			Requete::query($requete_mariage) or die (mysql_error()); 
			$requete_id_mariage='SELECT Max(ID) As max_id FROM mariages WHERE id_session='.Personne::$id_session;
			$resultat_id_mariage=Requete::query($requete_id_mariage) or die (mysql_error()); 
			if ($infos_id_mariage=mysql_fetch_array($resultat_id_mariage)) {
				$id_mariage=$infos_id_mariage['max_id'];
				foreach($mariage->enfants as $enfant) {
					$requete_enfant='INSERT INTO enfants_mariages(id_session,id_mariage,id_enfant) '
								   .'VALUES ('.Personne::$id_session.','.$id_mariage.',\''.$enfant.'\')';
					Requete::query($requete_enfant);
				}
			}
		}
	}
	
	function from_bd() {
		$champs_a_recuperer=array('id','naissance','date_naissance','lieu_naissance','date_mort','lieu_mort','mort','autres','prenom','nom','sexe','pere','mere');
		$requete='SELECT id_pos';
		foreach($champs_a_recuperer as $champ)
			$requete.=', `'.$champ.'`';
		$requete.=' FROM personnes WHERE id LIKE \''.$this->id.'\' AND id_session='.Personne::$id_session;
		echo $requete;
		$requete_resultat=Requete::query($requete) or die (mysql_error());
		if ($infos=mysql_fetch_array($requete_resultat)) {
			foreach($infos as $champ=>$valeur)
				$this->$champ=$valeur; 
			$requete_position_existe='SELECT x,y FROM positions WHERE id LIKE \''.$this->id.'\' AND id_session='.Personne::$id_session;
			$resultat_position_existe=Requete::query($requete_position_existe);
			if ($info_position=mysql_fetch_array($resultat_position_existe))
				$this->pos=new Coord($info_position['x'],$info_position['y']);
			else
				$this->pos=new Coord(0,0);	
			$champs_mariage_a_recuperer=array('conjoint1', 'conjoint2', 'date_mariage', 'lieu_mariage');
			$requete_mariages='SELECT id';
			foreach($champs_mariage_a_recuperer as $champ)
				$requete_mariages.=', `'.$champ.'`';
			$requete_mariages.=' FROM mariages WHERE id_session='.Personne::$id_session. ' AND (conjoint1 LIKE \''.$this->id.'\' OR conjoint2 LIKE \''.$this->id.'\')';
			$requete_mariages_resultat=Requete::query($requete_mariages) or die (mysql_error());
			$this->mariages=array();
			while ($infos_mariage=mysql_fetch_array($requete_mariages_resultat)) {
				$m=new Mariage();
				foreach($infos_mariage as $champ=>$valeur)
					$m->$champ=$valeur; 
				
				$requete_enfants_mariage='SELECT id_enfant FROM enfants_mariages '
										.'WHERE id_session='.Personne::$id_session.' AND id_mariage='.$m->id;
				$resultat_enfants_mariage=Requete::query($requete_enfants_mariage) or die (mysql_error());
				$m->enfants=array();
				while ($infos_enfant=mysql_fetch_array($resultat_enfants_mariage)) {
					$m->enfants[]=$infos_enfant['id_enfant'];
				}
				$this->mariages[]=$m;
			}
		}
		else return null;
		print_r($this);
		
	}
	
	function afficher() {
		echo '<u>'.$this->prenom.' '.strtoupper($this->nom).'</u> ('.$this->date_naissance.', '.$this->lieu_naissance.'-'.$this->date_mort.', '.$this->lieu_mort.'), '
			 .count($this->mariages).' mariage(s)<br />';
	}
	
	function setEtat($etat) {
		$requete='UPDATE personnes SET etat=\''.$etat.'\' WHERE id LIKE \''.$this->id.'\' AND id_session='.Personne::$id_session;
		Requete::query($requete) or die (mysql_error());
	}
	
	function getEtat() {
		$requete='SELECT etat FROM personnes WHERE id LIKE \''.$this->id.'\' AND id_session='.Personne::$id_session;
		$requete_resultat=Requete::query($requete) or die (mysql_error());
		while($infos=mysql_fetch_array($requete_resultat))
			return $infos['etat'];
	}
	
	function getBoite($id) {
		$requete='SELECT id, sexe, recursion, contenu, left, width, height, top FROM boites WHERE id LIKE \''.$id.'\' AND id_session='.Personne::$id_session;
		$resultat_requete=Requete::query($requete);
		if ($infos=mysql_fetch_array($resultat_requete)) {
			return $infos;
		} 
	}
	
	function getTrait($id, $id2, $id3) {
		$requete='SELECT id, id2, id3, pos_liaison_x, pos_liaison_y, bordertop, borderleft, left, width, top, height, label, name '
				.'FROM boites '
				.'WHERE id LIKE \''.$id.'\' AND id2 LIKE \''.$id2.'\' AND id LIKE \''.$id3.'\' AND id_session='.Personne::$id_session;
		$resultat_requete=Requete::query($requete);
		if ($infos=mysql_fetch_array($resultat_requete)) {
			$infos['pos_liaison']=new Coord($infos['pos_liaison_x'],$infos['pos_liaison_y']);
			$infos['border']=array('top'=>$infos['bordertop'],'left'=>$infos['borderleft']);
			return $infos;
		} 
	}
	
	function verifier_peut_parcourir() {
		$requete='SELECT etat FROM personnes WHERE id LIKE \''.$this->id.'\' AND etat NOT LIKE \'todo\' AND id_session='.Personne::$id_session;
		echo $requete;
		$requete_resultat=Requete::query($requete) or die (mysql_error());
		while($infos=mysql_fetch_array($requete_resultat))
			return false;//$infos['etat'];
		return true;
	}
	
	function verifier_peut_ecrire() {
		return !(in_array($this->id,Personne::$personnes_en_cours) || in_array($this->id,Personne::$personnes_ecrites));	
	}
	
	function serialiser($serveur,$pseudo,$autres_args) {
		$inF = fopen('serialize.personne_base.'.$serveur.'.'.$pseudo.'.'.$autres_args.'-'.LIMITE_PROFONDEUR_SNIFFER.'.txt',"w");
		fwrite($inF,serialize($this));
		fclose($inF);
		$inF = fopen('serialized.Liste.'.$serveur.'.'.$pseudo.'.'.$autres_args.'-'.LIMITE_PROFONDEUR_SNIFFER.'.txt',"w");
		fwrite($inF,serialize($this));
		fclose($inF);
	}
	
	function get_numero_personne() {
		return $this->id;
	}
	
	function get_numero_famille_origine() {
		foreach(Personne::$liste_familles as $i=>$famille) {
			foreach($famille->enfants as $enfant)
				if ($enfant == $this->id)
					return $i;
		}
		return -1;
	}
	
	function get_numeros_familles_souches() {
		$liste_familles=array();
		foreach(Personne::$liste_familles as $i=>$famille) {
			if ($famille->conjoint1 == $this->id || $famille->conjoint2 == $this->id)
				array_push($liste_familles,$i);
		}
		return $liste_familles;
	}
	
	function ecrire_gedcom() {
		$texte='';
		if ($this->url=='http://gw2.geneanet.org/index.php3?b=jboidier&lang=fr;p=francois;n=veurier;oc=4') {
			$a=1;
			$b=2;
		}
		if (!in_array($this->id,Personne::$personnes_en_cours)) array_push(Personne::$personnes_en_cours,$this->id);
		$texte.='0 @I'.$this->get_numero_personne().'@ INDI'."\n";
		$texte.= '1 NAME '.$this->prenom.'/'.$this->nom.'/'."\n";
		if ($this->sexe!='?')
			$texte.= '1 SEX '.$this->sexe."\n";
		if (!empty($this->autres))
			$texte.= '1 OCCU '.$this->autres."\n";
		$numero_famille_origine=$this->get_numero_famille_origine();
		if ($numero_famille_origine!=-1)
			$texte.= '1 FAMC @F'.$this->get_numero_famille_origine()."@\n";
		$familles_souches=$this->get_numeros_familles_souches();
		foreach($familles_souches as $id_famille)
			$texte.= '1 FAMS @F'.$id_famille."@\n";
		if (!($this->date_naissance===false && $this->lieu_naissance===false)) {
			$texte.= '1 BIRT'."\n";
			if (($this->date_naissance)!=-9999)
				$texte.=  '2 DATE '.$this->date_naissance."\n";
			if (!(empty($this->lieu_naissance) || $this->lieu_naissance==''))
				$texte.=  '2 PLAC '.$this->lieu_naissance."\n";
		}
		if (!($this->date_mort===false && $this->lieu_mort===false)) {
			$texte.= '1 DEAT'."\n";
			if (($this->date_mort)!=-9999)
				$texte.=  '2 DATE '.$this->date_mort."\n";
			if (!(empty($this->lieu_mort) || $this->lieu_mort==''))
				$texte.=  '2 PLAC '.$this->lieu_mort."\n";
		}
		$texte.= '1 NOTE '.$this->url.'. '.Personne::$note_supplementaire."\n";
		$pere=PersonneFromBD($this->pere);
		if (isset($pere) && $pere->verifier_peut_ecrire())
			$texte.=$pere->ecrire_gedcom();
		
		$mere=PersonneFromBD($this->mere);
		if (isset($mere) && $mere->verifier_peut_ecrire())
			$texte.=$mere->ecrire_gedcom();
		
		$mariages=$this->mariages;
		if (isset($mariages)) {
			foreach($mariages as $mariage) {
				$conjoint=PersonneFromBD($mariage->conjoint1==$this->id?$mariage->conjoint2:$mariage->conjoint1);
				if ($conjoint->verifier_peut_ecrire()) {
					$texte.=$conjoint->ecrire_gedcom();
					foreach($mariage->enfants as $id_enfant) {
						$enfant=PersonneFromBD($id_enfant);
						if ($enfant->verifier_peut_ecrire()) {
							$texte.=$enfant->ecrire_gedcom();
						}
					}
				}
			}
		}
			
		unset(Personne::$personnes_en_cours[array_search($this->id, Personne::$personnes_en_cours)]);
		if (!in_array($this->id,Personne::$personnes_ecrites)) array_push(Personne::$personnes_ecrites,$this->id);
		
		return $texte;
	}
	
	function dessinerArbre($priorite) {
		debug ( 'Analyse de '.$this->prenom.' '.$this->nom.' ('.$this->id.')');
		$this->analyser(true);return;
		
		
		if (count(debug_backtrace())>=LIMITE_PROFONDEUR-1-$priorite) {
			echo '[TMR]<br />';
			return false;
		}
		elseif (!array_key_exists($this->id,Personne::$liste_boites))
			$this->dessiner();
			
		if (count($this->mariages)>0) {
			$fin_enfants_precedents=0;
			foreach($this->mariages as $num_mariage=>$mariage) {
				$id_conjoint=$mariage->conjoint2;
				$conjoint=PersonneFromBD($id_conjoint);
				
				if (Personne::mariage_est_dessine($this->id,$id_conjoint))
					continue;
				array_push(Personne::$liste_mariages_dessines,array($this->to_id(),$id_conjoint));
				
				$conjoint->change_bd(array('pos'=>new Coord(-1,$this->pos->y)));
				if (array_key_exists(Personne::$niveau_courant+1,Personne::$liste_marges_gauches)
				   && $conjoint->pos->x < Personne::$liste_marges_gauches[Personne::$niveau_courant+1] 
				   && $this->pos->x>0 && count($mariage->enfants)>0) {
					$conjoint->change_bd(array('pos_x'=>Personne::$liste_marges_gauches[Personne::$niveau_courant+1]+ESPACEMENT_INCONNUS));
				}
				else {
					$conjoint->change_bd(array('pos_x'=>$this->pos->x+LARGEUR_PERSONNE+ESPACEMENT_EPOUX+((LARGEUR_PERSONNE+70)*$num_mariage)));
				}
				$this->lier_avec_conjoint($conjoint,$num_mariage,$mariage->date_mariage);
				$pos_liaison=new Coord((($conjoint->pos->x+LARGEUR_PERSONNE)-$this->pos->x)/2,$this->pos->y);
				$nb_enfants=count($mariage->enfants);
				$largeur_enfants=$nb_enfants*LARGEUR_PERSONNE+($nb_enfants==1?0:(($nb_enfants-1)*ESPACEMENT_ENFANT));
				$conjoint->dessinerArbre(0);
				Personne::$niveau_courant++;
				foreach($mariage->enfants as $num_enfant=>$id_enfant) {
					$enfant=PersonneFromBD($id_enfant);
					$enfant->change_bd(array('pos'=>
						new Coord(($fin_enfants_precedents>0?($fin_enfants_precedents):($pos_liaison->x-$largeur_enfants/2)) + $num_enfant*(LARGEUR_PERSONNE+ESPACEMENT_ENFANT),
								   $this->pos->y+HAUTEUR_PERSONNE+HAUTEUR_GENERATION)));
					$enfant->dessinerArbre(1);
					$this->lier_avec_enfant($conjoint,$num_mariage,$enfant,$conjoint->pos->x);
				}
				Personne::$niveau_courant--;
				if ($nb_enfants>0) {
					$dernier_enfant=PersonneFromBD($this->mariages[$num_mariage]->enfants[$nb_enfants-1]);
					$fin_enfants_precedents= $dernier_enfant->pos->x + LARGEUR_PERSONNE+ESPACEMENT_INCONNUS;
				}
			}
		}
			
		if ($this->pere!=null) {
			$pere=PersonneFromBD($this->pere);
			if ($pere->mariages!=null) {
				Personne::$niveau_courant--;
				foreach($pere->mariages as $num_mariage=>$mariage)
					foreach($mariage->enfants as $num_enfant=>$id_enfant)
						if ($id_enfant==$this->id)
							list($numero_mariage,$num_enfant)=array($num_mariage,$num_enfant);
				if (isset($numero_mariage)) {
					$nb_enfants=count($pere->mariages[$numero_mariage]->enfants);
					$largeur_enfants=$nb_enfants*LARGEUR_PERSONNE+($nb_enfants==1?0:(($nb_enfants-1)*ESPACEMENT_ENFANT));
					$pos_liaison=new Coord($this->pos->x + $largeur_enfants/2 - $num_enfant*(LARGEUR_PERSONNE+ESPACEMENT_ENFANT), $this->pos->y-HAUTEUR_GENERATION-HAUTEUR_PERSONNE/2);
					$pere->pos
						=new Coord($pos_liaison->x-ESPACEMENT_EPOUX/2-LARGEUR_PERSONNE,$pos_liaison->y-HAUTEUR_PERSONNE/2);
					if ($pere->dessinerArbre(1)) {
						//Vérifier que ces traits ne sont pas déjà dessinés
						$liaisons_deja_dessinees=false;
						if (Trait::traitEnfantExiste($this->pere, $this->mere, $this->id))
							$liaisons_deja_dessinees=true;
						if (!$liaisons_deja_dessinees) 
							$this->lier_avec_pere($pos_liaison,$numero_mariage,$this,$pere->pos->x+LARGEUR_PERSONNE+ESPACEMENT_EPOUX);
					}
				}
				Personne::$niveau_courant++;
			}
		}
		
		
		unset(Personne::$ids_en_cours[$this->id]);
		return true;
	}

	static function corriger_placement_epoux() {
		$couples=Trait::getEpouxMalPlaces();
		foreach($couples as $couple) {
			list($boite1,$boite2)=$couple;
			Personne::echanger_boites($boite1, $boite2);
		}
	}
	
	function modifierBoite($args) {
		$requete='UPDATE boites SET id='.$this->id;
		foreach($args as $id=>$value) {
			$requete.=', '.$id.'=\''.$value.'\'';
		}
		$requete.=' WHERE id=\''.$this->id.'\' AND id_session='.Personne::$id_session;
		Requete::query($requete) or die(mysql_error());
	}

	function ajouterTrait($args,$type_id) {
		$nom_champ_id=$type_id=='Chef de famille'?'id':'id3';
		$requete='INSERT INTO traits('.$nom_champ_id;
		foreach ($args as $index=>$value) {
			switch($index) {
				case 'border':$requete.=', border_top, border_left';
				break;
				case 'pos_liaison':$requete.=', pos_liaison_x, pos_liaison_y';
				break;
				default:$requete.=', '.$index;
			}
		}
		$requete.=', id_session) VALUES ('.$this->id;
		foreach ($args as $index=>$value) {
			switch($index) {
				case 'border':$requete.=','.$value[0].','.$value[1];
				break;
				case 'pos_liaison':$requete.=', '.$value->x.','.$value->y;
				break;
				default:$requete.=', \''.$index.'\'';
			}
		}
		$requete.=', '.Personne::$id_session.')';
		Requete::query($requete) or die(mysql_error());
	}
	
	function dessiner() {
		debug('<br />');
		debug('Création de '.$this->prenom.' '.$this->nom.'<br />');
		
		$marge_vide=new Marge();
		$marge= $marge_vide->get(array('niveau'=>Personne::$niveau_courant));
		if (!$marge) {
			$marge=new Marge(array('niveau'=>Personne::$niveau_courant,'marge'=>0));
			$marge->add();
		}
			
		if ($this->pos->x < $marge->marge) {
			$new_x=$marge->marge+LARGEUR_BORDURE*4+ESPACEMENT_INCONNUS;
			debug($this->prenom.' '.$this->nom.' : '.$this->pos->x.' ==> '.$new_x.',précédent à '.$marge->marge.'<br />');
			$this->pos->x=$new_x;
		}
		$boite=new Boite(array('id'=>$this->id,'sexe'=>$this->sexe,
							   'recursion'=>count(debug_backtrace()),
							   'contenu'=>$this->prenom.' '.strtoupper($this->nom).'<br /><span style="font-size:10px">'.$this->naissance.' - '.$this->mort.'</span>',
							   'pos'=>new Coord($this->pos->x,$this->pos->y),'dimension'=>new Dimension(LARGEUR_PERSONNE,HAUTEUR_PERSONNE)));
		$boite->add();
		$marge->setMarge($this->pos->x+LARGEUR_PERSONNE);
	}
	
	function lier_avec_conjoint($conjoint,$num_mariage,$date_mariage) {
		$debut_liaison=$this->pos->x+LARGEUR_PERSONNE+LARGEUR_BORDURE*4;
		$trait=new Trait(array(Trait::type_id_to_nom_champ('Conjoint')=>$this->id, 'id2'=>$conjoint->id,'id3'=>null,
							   'border'=>new Border(array('top'=>1,'left'=>0)),
							   'pos_debut'=>new Coord(array('x'=>$debut_liaison,'y'=>$this->pos->y+($num_mariage*ESPACEMENT_MARIAGES)+HAUTEUR_PERSONNE/2)),
							   'width'=>$conjoint->pos->x-$debut_liaison-4*LARGEUR_BORDURE,
							   'label'=>$date_mariage,
							   'name'=>'conjoint',
							   'type'=>'conjoints')
							);
		$trait->add();
	}
	
	function lier_avec_enfant($conjoint,$num_mariage,$enfant,$fin_enfants_precedents) { 
		debug('Liaison de '.$this->id.' avec son enfant '.$enfant->id.'<br />');
		if ($fin_enfants_precedents > 0)
			$pos_liaison=new Coord($fin_enfants_precedents-ESPACEMENT_EPOUX/2,HAUTEUR_PERSONNE/2+$this->pos->y+($num_mariage*ESPACEMENT_MARIAGES));
		else
			$pos_liaison=new Coord((($conjoint->pos->x+LARGEUR_PERSONNE)-$this->pos->x)/2,HAUTEUR_PERSONNE/2+$this->pos->y+($num_mariage*ESPACEMENT_MARIAGES));
		$pos_liaison=new PosLiaison(array('id1'=>$this->id, 'id2'=>$conjoint->id,$pos_liaison));
		$pos_liaison->addToBD();
		$pos_trait_enfant=new Coord($enfant->pos->x + LARGEUR_PERSONNE/2,$pos_liaison->y + HAUTEUR_PERSONNE/2+HAUTEUR_GENERATION/2);
		
		// Point de liaison <-> Ligne des enfants
		$trait=new Trait(array(Trait::type_id_to_nom_champ('Chef de famille')=>$this->id, 'id2'=>$conjoint->id,'id3'=>$enfant->id,
							   'pos_liaison'=>$pos_liaison,
							   'border'=>new Border(array('left'=>1)),
							   'pos_debut'=>$pos_liaison,
							   'height'=>$pos_trait_enfant->y-$pos_liaison->y,
							   'name'=>'enfant type1',
							   'type'=>'point_liaison__ligne_enfants')
							);
		$trait->add();
		if (array_key_exists($enfant->id,Personne::$liste_boites))
			$pos_reelle=Personne::id_to_pos($enfant->id);
		else
			$pos_reelle=$enfant->pos;
		// Ligne des enfants <-> Enfant
		$trait=new Trait(array(Trait::type_id_to_nom_champ('Chef de famille')=>$this->id,'id2'=>$conjoint->id,'id3'=>$enfant->id,
							   'pos_liaison'=>$pos_liaison,
							   'border'=>new Border(array('left'=>1)),
							   'pos_debut'=>new Coord(array('x'=>$pos_reelle->x+LARGEUR_PERSONNE/2,'y'=>$pos_trait_enfant->y)),
							   'height'=>HAUTEUR_GENERATION/2-$num_mariage*ESPACEMENT_MARIAGES,
							   'name'=>'enfant type2',
							   'type'=>'ligne_enfants__enfant')
							);
		$trait->add();
	}
	
	function lier_avec_pere($pos_liaison,$num_mariage,$enfant,$fin_enfants_precedents) { 
		debug('Liaison de '.$this->id.' avec son père '.$this->pere.'<br />');
		$pos_trait_enfant=new Coord($this->pos->x + LARGEUR_PERSONNE/2,$pos_liaison->y + HAUTEUR_PERSONNE/2+HAUTEUR_GENERATION/2-$num_mariage*ESPACEMENT_MARIAGES);
		// Point de liaison <-> Ligne des enfants
		$trait=new Trait(array(Trait::type_id_to_nom_champ('Enfant')=>$this->id,'id'=>$this->pere,'id2'=>$this->mere,
								  'pos_liaison'=>$pos_liaison,
								  'border'=>new Border(array('left'=>1)),
								  'pos_debut'=>$pos_liaison,
								  'height'=>$pos_trait_enfant->y-$pos_liaison->y,
								  'name'=>'pere type1',
								  'type'=>'point_liaison__ligne_enfants'));
		$trait->add();
		// Ligne des enfants <-> Enfant
		$trait=new Trait(array(Trait::type_id_to_nom_champ('Enfant')=>$this->id,'id'=>$this->pere,'id2'=>$this->mere,
								  'pos_liaison'=>$pos_liaison,
								  'border'=>new Border(array('left'=>1, 'top'=>0)),
								  'pos_debut'=>$pos_trait_enfant,
								  'height'=>HAUTEUR_GENERATION/2,
								  'name'=>'pere type2',
								  'type'=>'ligne_enfants__enfant'));
		$trait->add();/**
		
		??????
		$trait=new Trait(array(Trait::type_id_to_nom_champ('Enfant')=>$this->id,'id'=>$parents[0],'id2'=>$parents[1],'id3'=>null,
								 'pos_liaison'=>$position_liaison_parents->x,
								 'border'=>array('top'=>1),
								 'left'=>$gauche,'width'=>$droite-$gauche,'top'=>$position_liaison_parents->y+HAUTEUR_PERSONNE/2+HAUTEUR_GENERATION/2,
								 'type'=>'conjoints',
								 'name'=>'enfant type3'));
		**/
		$debut_trait=(($pos_liaison->x>$pos_trait_enfant->x)?$pos_trait_enfant:$pos_liaison);
	}
	
	static function echanger_boites (Boite $boite1, Boite $boite2) {
		$id1=$boite1->id;
		$id2=$boite2->id;
		debug('Echanger '.$boite1['contenu'].' et '.$boite2['contenu'].'<br />');
		
		foreach(Personne::$liste_boites as $id=>$personne) {
			if ($id == $id1) {
				$this->modifierBoite(array('left'=>$boite2['left'], 'top'=>$boite2['top']));
				continue;
			}
			elseif ($id == $id2) {
				$this->modifierBoite(array('left'=>$boite1['left'], 'top'=>$boite1['top']));
			}
		}
		$traits=Trait::getTraitsConcernesPar($id1,$id2);
		foreach($traits as $trait) {
			$trait2=new Trait();
			$concernes=$trait->getConcernes();
			switch(count($concernes)) {
				case 2:
				foreach($concernes as $num_concerne=>$concerne) {
					switch($num_concerne) {
						case 0:
						if ($concerne == $id1) {
							debug('('.$boite1['left'].','.$boite1['top'].')<br />');
							$trait2->pos_debut->x=$boite1['left']-ESPACEMENT_EPOUX;
							$trait2->width=abs($boite1['left']-$boite2['left'])-LARGEUR_PERSONNE-LARGEUR_BORDURE*4;
						}
						break;
						case 1:
						if ($concerne == $id2) {
							$trait2->pos_debut->x=$boite1['left']+LARGEUR_PERSONNE;
						}
						break;
					}
				}
				break;
				case 3:
					
				if (!isset($trait2->height))
					$trait2->height=0; 
				if ($concernes[2] == $id1) {
					switch($trait2->height) {
						case 0: // Trait horizontal entre les enfants
							if ($boite2['left']>$trait2->pos_debut->x) { // Si la personne à échanger est déplacée de la gauche vers la droite
								if ($boite2['left'] < $trait->pos_liaison->x) { // Si cet enfant est à droite du point de liaison
									$trait2->pos_debut->x=$boite2['left']+LARGEUR_PERSONNE/2;
									$trait2->width-=(LARGEUR_PERSONNE+ESPACEMENT_EPOUX);
								}
								else {
									$trait2->pos_debut->x=$trait2->pos_liaison->x;
									$trait2->width=$boite2['left']+LARGEUR_PERSONNE/2-$trait2->pos_liaison->x;
								}
							}
							else {
								$trait2->width=LARGEUR_PERSONNE+ESPACEMENT_EPOUX;
							}
						break;
						case HAUTEUR_GENERATION/2: // Petit trait vertical reliant à l'enfant
							$trait2->pos_debut->x=$boite2['left']+LARGEUR_PERSONNE/2;
						break;
					}
				}
				if ($concernes[2] == $id2) {
					switch($trait->height) {
						case 0: // Trait horizontal entre les enfants
							if ($boite1['left']>$trait2->pos_debut->x) { // Si la personne à échanger est déplacée de la gauche vers la droite
								if ($boite1['left'] < $trait2->pos_liaison->x) { // Si cet enfant est à droite du point de liaison
									$trait2->pos_debut->x=$boite1['left']+LARGEUR_PERSONNE/2;
									$trait2->width-=(LARGEUR_PERSONNE+ESPACEMENT_EPOUX);
								}
								else {
									$trait2->pos_debut->x=$trait2->pos_liaison->x;
									$trait2->width=($boite1['left']+LARGEUR_PERSONNE/2)-$trait2->pos_liaison->x;
								}
							}
							else {
								$trait2->width+=(LARGEUR_PERSONNE+ESPACEMENT_EPOUX);
							}
						break;
						case HAUTEUR_GENERATION/2: // Petit trait vertical reliant à l'enfant
							$trait2->pos_debut->x=$boite1['left']+LARGEUR_PERSONNE/2;
						break;
					}
				}
				break;
			}
			$trait->changeToBD();
		}
	}
	
	static function supprimer_traits_sans_issue() {
		Trait::supprimer_sans_issue();
	}
	
	static function corriger_traits() {
		$traits_horiz=array();
		$traits=Trait::getAll(array('id3 IS NOT NULL'));
		foreach($traits as $id_trait=>$trait) {
			$concernes=$trait->getConcernes();
			$parents=$concernes[0].'~'.$concernes[1];
			$id_enfant=$concernes[2];
			if (!strpos($trait['type'],'ligne_enfants__enfant')===FALSE) {
				if (!array_key_exists($parents,Personne::$liste_pos_liaisons))
					continue;
				$point_liaison_parents=Personne::$liste_pos_liaisons[$parents]->x;
				$boite_enfant=Boite::getBoite($id_enfant);
				$point_liaison_enfant=$boite_enfant->pos_debut->x+LARGEUR_PERSONNE/2;
				if ($point_liaison_parents<$point_liaison_enfant) {
					if (!array_key_exists($parents,$traits_horiz)) {
						$traits_horiz[$parents]['gauche']=$point_liaison_parents;
						$traits_horiz[$parents]['droite']=$point_liaison_enfant;
						continue;
					}
				}
				else {
					if (!array_key_exists($parents,$traits_horiz)) {
						$traits_horiz[$parents]['gauche']=$point_liaison_enfant;
						$traits_horiz[$parents]['droite']=$point_liaison_parents;
						continue;
					}
				}
				if ($traits_horiz[$parents]['gauche']>$point_liaison_enfant)
					$traits_horiz[$parents]['gauche']=$point_liaison_enfant;
				if ($traits_horiz[$parents]['droite']<$point_liaison_enfant)
					$traits_horiz[$parents]['droite']=$point_liaison_enfant;
			}
		}
		
		foreach($traits_horiz as $parents=>$valeurs) {
			$gauche=$valeurs['gauche'];
			$droite=$valeurs['droite']-4*LARGEUR_BORDURE;
			$position_liaison_parents=PosLiaison::get($parents[0],$parents[1]);
			
			Trait::ajouter(array('id'=>$parents[0],'id2'=>$parents[1],'id3'=>null,
								 'pos_liaison'=>$position_liaison_parents->x,
								 'border'=>array('top'=>1),
								 'left'=>$gauche,'width'=>$droite-$gauche,'top'=>$position_liaison_parents->y+HAUTEUR_PERSONNE/2+HAUTEUR_GENERATION/2,
								 'type'=>'conjoints',
								 'name'=>'enfant type3'));
		}
		
	}
	/* TODO : à revoir */
	static function corriger_lignes_horizontales() {
		$debut=microtime(true); 
		$tops_traits=array();
		$type2_concernes=array();
		$traits=Trait::getAll();
		foreach($traits as $id_trait=>$trait) {
			$concernes=$trait->getConcernes();
			if (isset($trait->height) && $trait->height!=0) {
				$type2_concernes[implode('~',$concernes)][$id_trait]=strpos($trait->name,'type2')!=FALSE;
			}
			else {
				if (!array_key_exists($trait->pos_debut->y,$tops_traits))
					$tops_traits[$trait->pos->debut->y]=array();
				$tops_traits[$trait->pos_debut->y][]=array('concernes'=>$concernes,'id_trait'=>$id_trait,'trait'=>$trait);
			}
			$trait->changeToBD();
		}
		
		foreach($tops_traits as $top=>$traits) {
			usort($traits,array('Personne','trier_lefts'));
			$marges_gauches=array();
			$parents_places=array();
			foreach($traits as $i=>$trait) {
				$trait2=$trait['trait'];
				$tab_concernes=$trait->getConcernes();
				$concernes=explode('~',$tab_concernes);
				$parents=array($concernes[0],$concernes[1]); 
				if (in_array($parents,$parents_places))
					break;
				$trouve=-1;
				for ($i=0;$i<((HAUTEUR_GENERATION/2)/ESPACEMENT_MARIAGES)-2;$i++) {
					if (!isset($marges_gauches[$i]) || $marges_gauches[$i]['marge']<$trait2['left']) {
						$trouve=$i;break;
					}
				}
				if ($trouve<=0)
					$parents_places[]=$parents;
					
				if ($trouve!=-1 && $trouve!=0) {
					$traits_couple=Trait::getTraitsCouple($tab_concernes[0],$tab_concernes[1]);
					$traits_couple[$trait['id_trait']]->pos_debut->y
						=Personne::$liste_traits[$trait['concernes']][$trait[$id_trait]]['top']+$trouve*ESPACEMENT_MARIAGES;
					$marges_gauches[$trouve]=array('parents'=>$parents,
											 	   'marge'=>$trait2['left']+$trait2['width']);
					
					foreach($type2_concernes as $concernes=>$traits) {
				  		$tab_concernes=explode('~',$concernes);
						if (($tab_concernes[0]==$parents[0] && $tab_concernes[1]==$parents[1])
					  	  ||($tab_concernes[0]==$parents[1] && $tab_concernes[1]==$parents[0])) {
							foreach($traits as $id_trait=>$est_type2) { // On décale les autres traits
								if ($est_type2) { // Trait des enfants <-> Trait de l'enfant
							  		Personne::$liste_traits[$concernes][$id_trait]['top']+=$trouve*ESPACEMENT_MARIAGES;
							  		Personne::$liste_traits[$concernes][$id_trait]['height']-=$trouve*ESPACEMENT_MARIAGES;
						  		}
						  		else // Trait de liaison <-> Trait des enfants
						  			Personne::$liste_traits[$concernes][$id_trait]['height']+=$trouve*ESPACEMENT_MARIAGES;
						  	}
					  	  }
					}
				}
				elseif ($trouve==0) {
					$marges_gauches[$trouve]=array('parents'=>$parents,
											 	   'marge'=>$trait2['left']+$trait2['width']);
				}
				else {
					
					echo 'Problème de placement pour '.$parents[0].'-'.$parents[1]
						.'Pensez à augmenter le paramètre HAUTEUR_GENERATION ou à diminuer ESPACEMENT_MARIAGES.<br />';
				}
			}
		}
	}
	
	static function trier_lefts($trait1,$trait2) {
		if ($trait1['trait']==$trait2['trait'])
			return 0;
		return ($trait1['trait'] > $trait2['trait']) ? +1 : -1;
	}
	
	static function corriger_cadrage($correction_gauche,$correction_haut) {
		$boites=Boite::getAll();
		foreach($boites as $boite) {
			if ($boite->pos->x < 0 && -1*$boite->pos->x > $correction_gauche)
				$correction_gauche=2 + -1*$boite->pos->x;
			if ($boite->pos->y < 0 && -1*$boite->pos->y > $correction_haut)
				$correction_haut=2 + -1*$boite->pos->y;
		}
		
		foreach($boites as $boite) {
			$boite->pos->x+=$correction_gauche;
			$boite->pos->y+=$correction_haut;
			$boite->changeToBD();
		}
		
		$traits=Trait::getAll();
		foreach($traits as $id_trait=>$trait) {
			$trait->pos->x+=$correction_gauche;
			$trait->pos->y+=$correction_haut;
		}
		
		debug('Décalage de '.$correction_gauche.' vers la droite et '.$correction_haut.' vers le bas<br />');
	}
	
	static function mariage_est_dessine($id_conjoint1,$id_conjoint2) {
		foreach(Personne::$liste_mariages_dessines as $mariage_dessine)
			if (($id_conjoint1==$mariage_dessine[0] && $id_conjoint2==$mariage_dessine[1]) 
			|| ($id_conjoint1==$mariage_dessine[1] && $id_conjoint2==$mariage_dessine[0]))
				return true;
		return false;
	}
	
	static function afficher_mariages() {
		foreach(Personne::$liste_mariages_dessines as $mariage_dessine) {
			$conjoint1=PersonneFromBD($mariage_dessine[0]);
			$conjoint2=PersonneFromBD($mariage_dessine[1]);
			echo $conjoint1->prenom.' '.$conjoint1->nom.' - '.$conjoint2->prenom.' '.$conjoint2->nom.'<br />';
		}
	}
	
	static function afficher_boites() {
		$boites=Boite::getAll();
		foreach($boites as $boite) {
			echo '<div id="'.implode('~',$boite->getConcernes).'" class="personne '.$boite->sexe;
			echo '" style="';
			foreach($boite as $nom_param=>$valeur_param)
				if (!in_array($nom_param,array('id','sexe','contenu','name','recursion','prefixes_objets')))
					echo $nom_param.':'.$valeur_param.'px;';
			echo '">'.$boite->contenu;
			echo '<div style="recursion">'.$boite->recursion.'</div>';
			echo '</div>';
		}
	
	}
	
	static function afficher_traits() {
		$traits=Trait::getAll();
		foreach($traits as $id_trait=>$trait) {
			$concernes=implode('~',$trait->getConcernes());
			echo '<div id="'.$concernes.'" class="trait" ';
			echo 'name="'.$trait->name.'" style="';
			foreach($trait->border as $position=>$taille)
				echo 'border-'.$position.':'.$taille.'px solid black;';
			foreach($trait as $nom_param=>$valeur_param)
				if (!in_array($nom_param,array('border','id','label','pos_liaison','name','prefixes_objets')))
					echo $nom_param.':'.$valeur_param.'px;';
			echo '">';
			if (!empty($trait->label)) echo Personne::date_to_year($trait->label);
			else echo '&nbsp;';
			echo '</div>';
			
		}
	}
	
	static function date_to_year($date) {
		$pos_dernier_slash=strrpos($date,'/');
		return substr($date,1+$pos_dernier_slash,strlen($date)-$pos_dernier_slash);
	}
	
	static function id_to_pos($id) {
		return new Coord(Personne::$liste_boites[$id]['left'],Personne::$liste_boites[$id]['left']);
	}
	
	static function url_to_id($url) {
		$regex='#&lang=fr;(.*)#is';
		preg_match($regex,$url,$resultat);
		if (count($resultat)==2)
			return $resultat[1];
		else
			return $url;
	}
	
	static function existe_en_bd($id) {
		$requete='SELECT id FROM personnes WHERE id LIKE \''.$id.'\'';
		$requete_resultat=Requete::query($requete);
		return mysql_num_rows($requete_resultat) >0;
	}
	
	static function initMake_tree() {
		$tables_a_effacer=array('boites','enfants_mariages','marges', 'mariages','positions', 'positions_liaisons','traits');
		foreach($tables_a_effacer as $table) {
			$requete_effacer='DELETE FROM '.$table.' WHERE id_session='.Personne::$id_session;
			Requete::query($requete_effacer) or die (mysql_error());
		}
	}
	
	static function setMarge($niveau,$marge) {
		$requete_set_marge='UPDATE marges SET marge='.$marge.' WHERE niveau='.$niveau.' AND id_session='.Personne::$id_session;
		Requete::query($requete_set_marge);
	}
	
	function to_id() {
		return Personne::url_to_id($this->url);
	}
}

function debug($texte) {
	if (DEBUG) {
		for($i=0;$i<count(debug_backtrace())-1;$i++)
			echo '&nbsp;';
		echo $texte;
	}
}

function decomposer_naissance_mort ($str) {
	$regex_date='#le&nbsp;([^&]+)&nbsp;([^&]+)&nbsp;([0-9]+)(?: julien)?#isu';
	$format_normal=preg_match($regex_date,$str,$r_date)!=0;
	if ($format_normal) {
		$jour=strlen($r_date[1])==1?'0'.$r_date[1]:$r_date[1];
		$jour=str_replace('er','',$jour);
		$mois=Personne::$mois[utf8_decode($r_date[2])];
		$annee=$r_date[3];
		$date=$jour.' '.$mois.' '.$annee;
	}
	else {
		$regex_date_courte='#(en|apr.s|avant|environ|vers|entre)&nbsp;([0-9]+)(?: julien)?(&nbsp;et&nbsp;([0-9]+)(?: julien)?)?#isu';
		$format_court=preg_match($regex_date_courte,$str,$r_date)!=0;
		if ($format_court) {
			$date=$r_date[2];
			switch($r_date[1]) {
				case 'avant': $date='BEF '.$date;break;
				case 'environ': 
				case 'vers':   $date='ABT '.$date;break;
				case 'en':break;
				default:$date='AFT '.$date;break;
			}
		}
		else {
			$regex_date_courte_mois='#(en|apr.s|avant|vers|environ|entre)&nbsp;([^&]+)&nbsp;([0-9]+)(?: julien)?(&nbsp;et&nbsp;([^&]+)&nbsp;([0-9]+)(?: julien)?)?#isu';
			$format_court_mois=preg_match($regex_date_courte_mois,$str,$r_date_mois)!=0;
			if ($format_court_mois) {
				$mois=Personne::$mois[utf8_decode($r_date[2])];
				$annee=$r_date[3];
				$date=$mois.' '.$annee;
				switch($r_date[1]) {
					case 'avant': $date='BEF '.$date;break;
					case 'environ': 
					case 'vers':   $date='ABT '.$date;break;
					case 'en':break;
					default:$date='AFT '.$date;break;
				}
			}
		}
	}
	if (!$format_normal && !$format_court) {
		if ($str==' ' || empty($str))
			$date=-9999;
		else {
			//echo 'Format de date inconnu pour '.$str.'<br />';
			$date='';
		}
		$r_date[0]=$str;
	}
	$regex_age_mort='# , . l\'.ge de [0-9]* (?:ans?|mois|jours?)#isu';
	$regex_nettoyage_lieu='#^ ?[-,]? ?#isu';
	if (!isset($r_date[0]))
		return array('','');
	
	$lieu=substr($str,strlen($r_date[0])+1,strlen($str)-1-strlen($r_date[0]));
	$age_mort_trouve=preg_match($regex_age_mort,$lieu,$r_lieu)!=0;
	$nettoyage_necessaire=preg_match($regex_nettoyage_lieu,$lieu,$r_lieu2)!=0;
	if ($age_mort_trouve)
		$lieu=substr($lieu,0,strlen($lieu)-strlen($r_lieu[0]));
	if ($nettoyage_necessaire)
		$lieu=substr($lieu,strlen($r_lieu2[0]),strlen($lieu)-strlen($r_lieu2[0]));
	if (!$lieu) $lieu='';
	return array($date,$lieu);
}

if (isset($_POST['analyse'])) {
	Personne::$id_session=$_POST['id_session']; 
	$url='http://'.$_POST['serveur'].'.geneanet.org/index.php3?b='.$_POST['pseudo'].'&lang=fr;'.str_replace(';pcnt;','%',$_POST['autres_args']);
	$p = new Personne($url);
	header("X-JSON: " . json_encode($p->analyser()));
}
elseif (isset($_POST['make_tree'])) {
	Personne::$id_session=$_POST['id_session']; 
	$url='http://'.$_POST['serveur'].'.geneanet.org/index.php3?b='.$_POST['pseudo'].'&lang=fr;'.str_replace(';pcnt;','%',$_POST['autres_args']);
	$url=urldecode($url);
	Personne::initMake_tree();
	$p = new Personne($url);
	header("X-JSON: " . json_encode($p->analyser(true)));
}

?>