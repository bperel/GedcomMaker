<?php
//require_once('FirePHPCore/FirePHP.class.php');
//$firephp = FirePHP::getInstance(true);
//
//require_once('FirePHPCore/fb.php');
//$firephp->registerErrorHandler(
//            $throwErrorExceptions=true);
//$firephp->registerExceptionHandler();
//$firephp->registerAssertionHandler(
//            $convertAssertionErrorsToExceptions=true,
//            $throwAssertionExceptions=false);
//
//$firephp->log('Plain Message');

/*if (!isset($_POST['id_session']))
	$_POST=array('caller'=>'', 'autres_args'=>'p=jean;n=veurier;oc=2',
				 'id_session'=>1271842690,'analyse'=>true,'pseudo'=>'jboidier','serveur'=>'gw2');
*/
$server='localhost';
$user='root';
$database='gedcommaker2';
$password='';
mysql_connect($server, $user, $password);
mysql_select_db($database);
date_default_timezone_set('UTC');
include_once('ComplexObject.class.php');
include_once('Util/Requete.class.php');

include_once('Coord.class.php');
include_once('Border.class.php');
include_once('Dimension.class.php');
include_once('Liaison.class.php');

include_once('Boite.class.php');
include_once('Trait.class.php');
include_once('Marge.class.php');
include_once('Level.class.php');

include_once('Mariage.class.php');
include_once('EnfantMariage.class.php');

include_once('sites/Geneanet.class.php');

define('DEBUG',isset($_GET['debug']));
define('LARGEUR_PERSONNE',150);
define('HAUTEUR_PERSONNE',100);
define('HAUTEUR_GENERATION',120);
define('ESPACEMENT_MARIAGES',4);
define('ESPACEMENT_ENFANT',40);
define('ESPACEMENT_INCONNUS',60);
define('ESPACEMENT_EPOUX',50);
define('LARGEUR_BORDURE',1);

define('LIMITE_PROFONDEUR_SNIFFER',99);
define('LIMITE_PROFONDEUR',LIMITE_PROFONDEUR_SNIFFER-2);

define('COUPLE_PREMIER_MARIAGE',1);
define('COUPLE_REMARIE',0);

ini_set ('max_execution_time', 0); 

function PersonneFromBD($id){
	$p=new Personne($id);
	if (is_null($p->ComplexMyselfToGet())) {
		return null;
	}
	return $p;
}

class Personne extends ComplexObject{
	static $prefixes_objets=array('boite'=>'Boite');
	static $identifiants=array('id');
	static $traitement_special=array('boite','mariages','url','url_parents');


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
	var $sexe='I';
	var $pere=null;
	var $mere=null;
        var $etat;
        var $url_parents=null;
	var $mariages=array();
	var $boite;

	static $retour=array();
	
	// Construction graphique de l'arbre
	static $liste_liaisons=array();
	
	static $niveau_courant;
        static $id_depart;
	static $id_session;
	static $liste_familles=array();
	static $ids_parcourus=array();
	static $ids_en_cours=array();
	static $personnes_ecrites=array();
	static $personnes_en_cours=array();
	
        static $site_source;
        
	static $note_supplementaire;
	
	static function setIdSession($id_session) {
		self::$id_session=$id_session;
	}
	
	static function getIdSession() {
		return self::$id_session;
	}
	
	function Personne($url=null,$sexe='I', $naissance='',$mort='',$autres='',$prenom='',$nom='',$pere='',$mere='') {
		$this->url=$url;$this->naissance=$naissance;$this->mort=$mort;$this->autres=$autres;
		$this->prenom=$prenom;$this->nom=$nom;
		$this->pere=$pere;
		$this->mere=$mere;
                switch(Personne::$site_source) {
                    case 'Geneanet' :
                        $this->id=Geneanet::url_to_id($this->url);
                        break;
                }
	}

        function get($filtres, $str_all=false) {
		$this->boite=ComplexObjectToGet('Boite', array('id'=>$this->id));
                $mariages1=ComplexObjectToGet('Mariage',array('conjoint1'=>$this->id));
                $mariages2=ComplexObjectToGet('Mariage',array('conjoint2'=>$this->id));
                if (is_null($mariages1)) {
                    if (is_null($mariages2))
			$this->mariages=array();
                    else
                        $this->mariages=$mariages2;
                }
                elseif (is_null($mariages2))
                    $this->mariages=$mariages1;
                else
                    $this->mariages=array_merge($mariages1, $mariages2);
		return parent::get($filtres,$str_all);
	}

        function add() {
            if (is_null($this->boite)) {
                echo 'Aucune boite d�finie pour '.$this->id."\n";
            }
            else
                $this->boite->addOrUpdate();
        }
        
	function ComplexMyselfToGet() {
            $p=new Personne($this->id);
            $o=$p->get(array('id'=>$this->id));
            if (is_null($o))
                return null;
            foreach($o as $attribut=>$valeur)
                $this->$attribut=$valeur;
        }
	function analyser($ComplexMyselfToGet=false) {
		if (LIMITE_PROFONDEUR_SNIFFER!=0 && count(debug_backtrace())>LIMITE_PROFONDEUR_SNIFFER) {
			echo '[TMR]<br />';
			return;
		}
		Personne::$retour['boites']=array('creation'=>array(),'modif'=>array());
		Personne::$retour['traits']=array('creation'=>array(),'modif'=>array());
		
                $this->ComplexMyselfToGet();


                $ch = curl_init($this->url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
		$page = curl_exec($ch);
		curl_close($ch);
                
                /*$handle = fopen($this->url, "rb");
                $page = stream_get_contents($handle);
                fclose($handle);*/
                if (is_null($page) || $page===false) {
                    fatal_error('Connection problem with '.$this->url.', exiting...');
                }
		if ($this->estPremiereAnalyse())
			Personne::$niveau_courant=0;
		else {
                    // R�cup�rer la relation avec la personne appelante
                    $id_personne_appelante=$_POST['caller'];
                    $personne_appelante=new Personne(str_replace(';pcnt;','%',$id_personne_appelante));
                    $personne_appelante->ComplexMyselfToGet();
                    $personne_appelante->boite->fixNiveauCourant();
                    Personne::$niveau_courant+=$this->getDifferenceNiveauAvec($id_personne_appelante);
		}

                new Personne::$site_source($page, $this);
		
		if (!(ComplexObjectExists('Boite',array('id'=>$this->id)))) {
                    Personne::$retour['boites']['creation'][]=$this->dessiner();
                    if (is_null($this->boite))
                            echo 'Boite non cr��e';
                }
		else {
			$this->genererBoite(ComplexObjectToGet('Boite',array('id'=>$this->id))->pos);
			Personne::$retour['boites']['modif'][]=$this->boite;
		}
		$this->boite->addOrUpdate();
                if (count($this->mariages)>0) {
                    $fin_enfants_precedents=0;
                    foreach($this->mariages as $i=>$mariage) {
                        $id_conjoint=$this->sexe=='H'?$mariage->conjoint2 : $mariage->conjoint1;
                        $conjoint=new Personne($id_conjoint,'I','?','?','',$id_conjoint,'...',null,null);
                        list($id_homme,$id_femme)=Personne::toHomme_Femme($this,$id_conjoint);
                        $conjoint->sexe=($id_conjoint==$id_homme)?'H':'F';
                        $conjoint_existe_bd=ComplexObjectExists('Personne', array('id'=>$id_conjoint));
                        if ($conjoint_existe_bd) {
                            $conjoint->ComplexMyselfToGet();
                            $liaison=ComplexObjectFieldToGet('Trait','liaison',array('id'=>$id_homme,'id2'=>$id_femme,'type'=>'conjoints'));
                            $pos_conjoint=$conjoint->boite->pos;
                            $action_conjoints='modif';
                        }
                        else {
                            $pos_conjoint=new Coord(array('x'=>$this->boite->pos->x,'y'=>$this->boite->pos->y));
                            $pos_conjoint->x+=($conjoint->sexe=='F' ? 1 : -1)*(LARGEUR_PERSONNE+ESPACEMENT_EPOUX+LARGEUR_BORDURE*4);
                            $action_conjoints='creation';
                            Personne::$retour['boites'][$action_conjoints][]=$conjoint->dessiner($pos_conjoint,$i==0?COUPLE_PREMIER_MARIAGE:COUPLE_REMARIE);
                        }

                        $homme=$this->sexe=='H' ? $this : $conjoint;
                        $femme=$this->sexe=='H' ? $conjoint : $this;
                        $liaison=Personne::calculerLiaison($homme,$femme,$i, $fin_enfants_precedents);
                        $liaison->addOrUpdate();
                        
                        Personne::$retour['mariages'][$i]['conjoint']=array('id'=>$id_conjoint,
                                                                            'action'=>$conjoint_existe_bd ? 'already_done' : 'todo');
                        
                        $mariage->addOrUpdate();
                        $mariage=ComplexObjectToGet('Mariage',array('conjoint1'=>$id_homme,'conjoint2'=>$id_femme)); // Pour r�cup�rer l'ID

                        Personne::ajouter_a_retour('trait', $action_conjoints, $this->lier_avec_conjoint($conjoint,$i, $mariage->date_mariage));
                        if (count($mariage->enfants) > 0) {
                            Personne::ajouter_a_retour('trait', $action_conjoints, $this->lier_avec_trait_enfants($conjoint,$i,$mariage,$fin_enfants_precedents));
                            $liaison=ComplexObjectToGet('Liaison', array('id'=>$id_homme,'id2'=>$id_femme));
                            $largeur_enfants=LARGEUR_PERSONNE*count($mariage->enfants) + ESPACEMENT_ENFANT*(count($mariage->enfants)-1);
                            Personne::$niveau_courant++;
                            foreach($mariage->enfants as $j=>$id_enfant) {
                                $enfant=new Personne($id_enfant,'I','?','?','',$id_enfant,'...',null,null);
                                $pos_enfant=new Coord(array('x'=>($fin_enfants_precedents>0?($fin_enfants_precedents):($liaison->pos->x-$largeur_enfants/2)) + $j*(LARGEUR_PERSONNE+ESPACEMENT_ENFANT),
                                                            'y'=>$this->boite->pos->y+HAUTEUR_PERSONNE+HAUTEUR_GENERATION));
                                $enfant_existe_en_bd=ComplexObjectExists('Personne',array('id'=>$enfant->id));
                                $action_enfant=$enfant_existe_en_bd?'modif':'creation';
                                Personne::$retour['boites'][$action_enfant][]=$enfant->dessiner($pos_enfant);
                                Personne::ajouter_a_retour('trait', $action_enfant, $this->lier_avec_enfant($conjoint,$i,$enfant,$conjoint->boite->pos->x));
                                if ($action_enfant=='creation')
                                    $enfant->addOrUpdate(); // Puis on ajoute l'enfant en tant que Personne...
                                Personne::$retour['mariages'][$i]['enfants'][$j]=array('id'=>$enfant->id,'action'=>$action_enfant=='creation'?'todo':'already_done');
                                if (!(ComplexObjectExists('EnfantMariage',array('id_enfant'=>$enfant->id, 'id_mariage'=>$mariage->id)))) {
                                    $o_enfant=new EnfantMariage(array('id_enfant'=>$enfant->id, 'id_mariage'=>$mariage->id));
                                    $o_enfant->add(); // ... Et l'enfant en tant que relation avec ses parents
                                }
                            }
                            Personne::$niveau_courant--;
                            Personne::ajouter_a_retour('trait', $action_conjoints, $this->lier_avec_enfants($conjoint,$i,$mariage,$fin_enfants_precedents));
                            $mariage->update();
                        }

                        $famille_existante=false;
                        foreach(Personne::$liste_familles as $famille) {
                            if ($famille_existante) break;
                            if (($famille->conjoint1==$this->id && $famille->conjoint2==$url_conjoint)
                                    ||($famille->conjoint1==$url_conjoint && $famille->conjoint2==$this->id))
                                $famille_existante=true;
                        }
                        if (!$famille_existante)
                            array_push(Personne::$liste_familles,$mariage);

                        if (count($mariage->enfants)>0) {
                            $id_dernier_enfant=$this->mariages[$i]->enfants[count($this->mariages[$i]->enfants)-1];
                            $boite_dernier_enfant=ComplexObjectToGet('boite',array('id'=>$id_dernier_enfant));
                            $fin_enfants_precedents= $boite_dernier_enfant->pos->x + LARGEUR_PERSONNE+ESPACEMENT_INCONNUS;
                        }
                        $conjoint->addOrUpdate();
                    }
                }
		if (!empty($this->pere) ||!empty($this->mere)) {
                    $pere=new Personne($this->pere,'H','?','?','',$this->pere,'?',null,null);
                    $liste_parents=array('pere','mere');
                    foreach($liste_parents as $parent) {
                        if (!is_null($this->$parent)) {
                            Personne::$retour[$parent]=array('id'=>$this->$parent,
                                                             'action'=>Personne::verifier_peut_parcourir($this->$parent)?'todo':'already_done');
                            $boite_parent=ComplexObjectToGet('Boite',array('id'=>$this->$parent));

                            if (!is_null($boite_parent)/* && $o_parent->etat=='make_tree'*/) { // Le parent est dans la base de donn�es => On a toutes les informations sur lui
                                $mariages_pere=ComplexObjectToGet('Mariage',array('conjoint1'=>$this->pere),'all');
                                $numero_mariage=Mariage::getMariageConcerne($mariages_pere,$this->id);
                                $mariage=$mariages_pere[$numero_mariage];
                                if (isset($numero_mariage)) {
                                    $enfants_mariage=ComplexObjectToGet('EnfantMariage', array('id_mariage'=>$mariage->id),'all');
                                    $ids_enfants=array();
                                    foreach($enfants_mariage as $enfant_mariage)
                                        $ids_enfants[]=$enfant_mariage->id_enfant;
                                    $nb_enfants=count($enfants_mariage);
                                    $numero_enfant_fratrie=EnfantMariage::getNumeroEnfantFratrie($enfants_mariage,$this->id);
                                    $largeur_enfants=$nb_enfants*LARGEUR_PERSONNE+($nb_enfants==0?0:(($nb_enfants-1)*ESPACEMENT_ENFANT));
                                    $coords_liaison
                                            =new Coord(array('x'=>$this->boite->pos->x + $largeur_enfants/2 - $numero_enfant_fratrie*(LARGEUR_PERSONNE+ESPACEMENT_ENFANT),
                                                             'y'=>$this->boite->pos->y-HAUTEUR_GENERATION-HAUTEUR_PERSONNE/2));
                                    $boite_parent->pos
                                            =new Coord(array('x'=>$coords_liaison->x-ESPACEMENT_EPOUX/2-LARGEUR_PERSONNE,
                                                             'y'=>$coords_liaison->y-HAUTEUR_PERSONNE/2));
                                    $boite_parent->update();
                                    //V�rifier que ces traits ne sont pas d�j� dessin�s
                                    if (!(ComplexObjectExists('Trait',array('id'=>$this->pere, 'id2'=>$this->mere, 'id3'=>$this->id))) && $parent=='pere') {
                                        $liaison=new Liaison(array('id'=>$this->pere, 'id2'=>$this->mere,'pos'=>$coords_liaison));
                                        $liaison->update();
                                        Personne::ajouter_a_retour('trait', 'modif', $this->lier_avec_pere($pere,$liaison,$ids_enfants,$numero_mariage));
                                    }
                                }
                                else
                                    echo 'Mariage de '.$this->pere.' ayant donn� '.$this->id.' non trouv� !';
                            }
                            else { // Le p�re n'a pas encore �t� parcouru => On fait avec les infos qu'on a
                                /*$this->ComplexMyselfToGet();
						$champs_utf8=array('prenom','nom','autres','naissance','mort','lieu_naissance','lieu_mort');
						foreach($champs_utf8 as $champ)
							$this->$champ=utf8_decode($this->$champ);*/
                                $nb_enfants_parent=1;
                                $numero_enfant_fratrie=0;
                                $largeur_enfants=LARGEUR_PERSONNE;

                                $coord_liaison_parents
                                        =new Coord(array('x'=>$this->boite->pos->x + $largeur_enfants/2,
                                                         'y'=>$this->boite->pos->y-HAUTEUR_GENERATION-HAUTEUR_PERSONNE/2));

                                $o_parent=new Personne($this->$parent,'I','?','?','','?',null,null);
                                $o_parent->prenom=$o_parent->id;
                                $o_parent->nom='...';
                                $o_parent->sexe=($parent=='pere')?'H':'F';
                                $pos_boite=new Coord(array('x'=>$coord_liaison_parents->x+($parent=='pere'?(-1*(ESPACEMENT_EPOUX/2+LARGEUR_PERSONNE)):ESPACEMENT_EPOUX/2),
                                                           'y'=>$coord_liaison_parents->y-HAUTEUR_PERSONNE/2));
                                Personne::$retour['boites']['creation'][]=$o_parent->dessiner($pos_boite);

                                $o_parent->addOrUpdate();
                                $mariage_parents=new Mariage(array('id'=>'','conjoint1'=>$this->pere,'conjoint2'=>$this->mere,'date_mariage'=>'','lieu_mariage'=>''));
                                $mariage_parents->enfants[0]=$this->id;
                                $o_parent->mariages[0]=$mariage_parents;
                                if ($parent=='pere') {
                                    $liaison_parents=new Liaison(array('id'=>$this->pere,'id2'=>$this->mere,'pos'=>$coord_liaison_parents));
                                    Personne::ajouter_a_retour('trait', 'creation', $this->lier_avec_pere($o_parent,$liaison_parents,array($this->id),0));
                                }
                                else {
                                    $mariage_parents->add();
                                    $mariage_parents=ComplexObjectToGet('Mariage',array('conjoint1'=>$this->pere,'conjoint2'=>$this->mere));

                                    $boite_pere=ComplexObjectToGet('Boite',array('id'=>$this->pere));
                                    $pere=new Personne($this->pere,'H','?','?','',$pere->id,'...',null,null);
                                    $pere->boite=$boite_pere;
                                    Personne::ajouter_a_retour('trait', 'creation', $o_parent->lier_avec_conjoint($pere,0, ''));
                                }
                            }
                        }
                    }
                }
		Personne::$niveau_courant++;

		$this->etat='make_tree';
		$this->addOrUpdate();
		Personne::corriger_cadrage(0,0);
		return Personne::$retour;
		
	}
	
	function getDifferenceNiveauAvec($id_personne) {
		$requete_pere_mere='SELECT pere, mere FROM personnes WHERE id LIKE \''.$id_personne.'\' AND id_session='.Personne::$id_session;
		$resultat_pere_mere=Requete::query($requete_pere_mere);
		while ($infos=mysql_fetch_array($resultat_pere_mere)) {
			if ($infos['pere']===$this->id || $infos['mere']===$this->id)
				return -1;
		}
		
		$requete_conjoint='SELECT conjoint1, conjoint2, id FROM mariages WHERE conjoint1 LIKE \''.$id_personne.'\' OR conjoint2 LIKE \''.$id_personne.'\'';
		$resultat_conjoint=Requete::query($requete_conjoint);
		while ($mariage=mysql_fetch_array($resultat_conjoint)) {
			if (($mariage['conjoint1']===$this->id && $mariage['conjoint2']===$id_personne) || 
				($mariage['conjoint2']===$this->id && $mariage['conjoint1']===$id_personne))
				return 0;
			$enfants_couple=ComplexObjectToGet('EnfantMariage',array('id_mariage'=>$mariage['id']),'all');
			if (!is_null($enfants_couple)) {
				if (!is_array(($enfants_couple))) $enfants_couple=array($enfants_couple);
				foreach($enfants_couple as $enfant_couple)
					if ($enfant_couple->id_enfant === $this->id)
						return 1;
			}
		}
		echo 'Aucune relation trouv�e entre '.$id_personne.' (appelant) et '.$this->id;
		exit(0);
	}
	
	function estPremiereAnalyse() {
		$requete_personnes='SELECT Count(id) AS cpt FROM personnes WHERE id_session='.Personne::$id_session;
		$resultat_requete_personnes=Requete::query($requete_personnes);
		if ($info_nb_personnes=mysql_fetch_array($resultat_requete_personnes))
			return $info_nb_personnes['cpt'] ==0 ;
	}
	
	static function toHomme_Femme(Personne $p1, $p2_id) {
		$homme_id=$p1->sexe=='H' || $p1->sexe!='F' ? $p1->id:$p2_id;
		$femme_id=$p1->sexe=='H' || $p1->sexe!='F' ? $p2_id:$p1->id;
		
		return array($homme_id,$femme_id);
	}
        
	function afficher() {
		echo '<u>'.$this->prenom.' '.strtoupper($this->nom).'</u> ('.$this->date_naissance.', '.$this->lieu_naissance.'-'.$this->date_mort.', '.$this->lieu_mort.'), '
			 .count($this->mariages).' mariage(s)<br />';
	}
	
	static function verifier_peut_parcourir($id) {
		$requete='SELECT etat FROM personnes WHERE id LIKE \''.$id.'\' AND etat NOT LIKE \'todo\' AND id_session='.Personne::$id_session;
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

	static function corriger_placement_epoux() {
		$couples=Trait::getEpouxMalPlaces();
		foreach($couples as $couple) {
			list($boite1,$boite2)=$couple;
			Personne::echanger_boites($boite1, $boite2);
		}
	}
	
	function dessiner($pos_boite=null, $premier_mariage=false) {
		debug('<br />');
		debug('Cr�ation de '.$this->prenom.' '.$this->nom.'<br />');
		$marge_gauche= ComplexObjectToGet('Marge',array('niveau'=>Personne::$niveau_courant));
		if (!$marge_gauche) {
			echo 'Marge gauche creee';
			$marge_gauche=new Marge(array('niveau'=>Personne::$niveau_courant,'marge'=>0));
			$marge_gauche->add();
		}
		if (is_null($pos_boite)) {
			$coord_boite=new Coord(array('x'=>$marge_gauche->marge,
                                                     'y'=>Personne::$niveau_courant*(HAUTEUR_PERSONNE+HAUTEUR_GENERATION)));
			
			if ($coord_boite->x>0) {
				debug($this->prenom.' '.$this->nom
					 .' ==> '.$coord_boite->x+LARGEUR_BORDURE*4+ESPACEMENT_INCONNUS.', au lieu de '.$marge_boite->x.'<br />');
				$coord_boite->x+=LARGEUR_BORDURE*4+ESPACEMENT_INCONNUS;
			}
		}
		else
			$coord_boite=$pos_boite;
                if ($premier_mariage) { // Premier mariage => d�caler les autres boites
                    $boite_existante=true;
                    $coord_boite_a_deplacer=clone $coord_boite;
                    if ($boite_existante) {
                        $coord_boite_a_deplacer->x += LARGEUR_PERSONNE + ESPACEMENT_INCONNUS;
                        $boites_gauche_a_deplacer=ComplexObjectToGet('Boite',array('pos_x>'.($coord_boite->x - (LARGEUR_PERSONNE)),
                                                                                   'pos_x<'.($coord_boite->x),
                                                                                   'pos_y>'.($coord_boite->y - (HAUTEUR_PERSONNE+HAUTEUR_GENERATION)),
                                                                                   'pos_y<'.($coord_boite->y + (HAUTEUR_PERSONNE+HAUTEUR_GENERATION))),
                                                                     'all');
                        if (!is_null($boites_gauche_a_deplacer)) {
                            foreach($boites_gauche_a_deplacer as $boite_a_deplacer) {
                                $boite_a_deplacer->deplacerExistanteVers(new Coord(array('x'=>$coord_boite->x+LARGEUR_PERSONNE +  ESPACEMENT_INCONNUS,'y'=>0)));
                            }
                        }
                        else
                            $boite_existante=false;
                    }
                }
                else { // Sinon, c'est la nouvelle boite qui doit �tre d�plac�e
                    $boite_existante=true;
                    while ($boite_existante) {
                        if (ComplexObjectExists('Boite',array('pos_x>'.($coord_boite->x - (LARGEUR_PERSONNE)),
                                                              'pos_x<'.($coord_boite->x + (LARGEUR_PERSONNE)),
                                                              'pos_y>'.($coord_boite->y - (HAUTEUR_PERSONNE+HAUTEUR_GENERATION)),
                                                              'pos_y<'.($coord_boite->y + (HAUTEUR_PERSONNE+HAUTEUR_GENERATION)))))
                            $coord_boite->x += LARGEUR_PERSONNE;
                        else
                            $boite_existante=false;
                    }
                }
		$this->genererBoite($coord_boite);
		if ($marge_gauche->marge < $this->boite->pos->x+LARGEUR_PERSONNE) {
			$marge_gauche->marge=$this->boite->pos->x+LARGEUR_PERSONNE;
			$marge_gauche->update();
		}
		return $this->boite;
	}
	
	function genererBoite($pos) {
		$this->boite= new Boite(array('id'=>$this->id,'sexe'=>$this->id===Personne::$id_depart ? $this->sexe : 'I',
                                              'recursion'=>count(debug_backtrace()),
                                              'contenu'=>$this->prenom.' '.strtoupper($this->nom).'<br /><span style="font-size:10px">'.$this->naissance.' - '.$this->mort.'</span>',
                                              'pos'=>$pos,'dimension'=>new Dimension(LARGEUR_PERSONNE,HAUTEUR_PERSONNE)));
	}
        
	static function calculerLiaison($homme, $femme,$num_mariage,$fin_enfants_precedents) {
            echo 'Pos liaison '.$homme->id.' - '.$femme->id.' : ';
            $coord_y=$homme->boite->pos->y+HAUTEUR_PERSONNE/2+($num_mariage*ESPACEMENT_MARIAGES);
            if ($fin_enfants_precedents > 0)
                $coord= new Coord(array('x'=>$fin_enfants_precedents-ESPACEMENT_EPOUX/2,
                                        'y'=>$coord_y));
            else
                $coord= new Coord(array('x'=>(($femme->boite->pos->x)-($homme->boite->pos->x + LARGEUR_PERSONNE))/2,
                                        'y'=>$coord_y));
            return new Liaison(array('id'=>$homme->id,'id2'=>$femme->id,'pos'=>$coord));
        }

	function lier_avec_conjoint(Personne $conjoint, $num_mariage, $date_mariage) {
		$personne_gauche=$this->boite->pos->x < $conjoint->boite->pos->x ? $this : $conjoint;
		$personne_droite=$personne_gauche==$this?$conjoint:$this;
		$id_homme=$personne_gauche->id;
		$id_femme=$personne_droite->id;
		$debut_liaison=$personne_gauche->boite->pos->x+LARGEUR_PERSONNE+LARGEUR_BORDURE*4;
                $liaison=new Liaison(array('id'=>$id_homme,'id2'=>$id_femme,
                                           'pos'=>new Coord(array('x'=>$debut_liaison+ESPACEMENT_EPOUX/2,
                                                                  'y'=>$this->boite->pos->y+($num_mariage*ESPACEMENT_MARIAGES)+HAUTEUR_PERSONNE/2))));
		$liaison->addOrUpdate();
                $trait=new Trait(array('id'=>$id_homme, 'id2'=>$id_femme,
                                       'border'=>new Border(array('top'=>1,'left'=>0)),
                                       'liaison'=>$liaison,
                                       'pos_debut'=>new Coord(array('x'=>$debut_liaison,'y'=>$this->boite->pos->y+($num_mariage*ESPACEMENT_MARIAGES)+HAUTEUR_PERSONNE/2)),
                                       'width'=>$personne_droite->boite->pos->x-$personne_gauche->boite->pos->x-LARGEUR_PERSONNE-LARGEUR_BORDURE*2*4,
                                       'label'=>$date_mariage,
                                       'name'=>'liaison',
                                       'type'=>'conjoints')
                                    );
		$trait->addOrUpdate();
		return array($trait);
	}
	
	function lier_avec_trait_enfants($conjoint,$num_mariage,$fin_enfants_precedents) {
            $id_pere=$this->sexe=='H'?$this->id:$conjoint->id;
            $id_mere=$this->sexe!='H'?$this->id:$conjoint->id;
            $traits=array();
            $liaison=ComplexObjectToGet('Liaison', array('id'=>$id_pere,'id2'=>$id_mere));
            $pos_trait_enfants_y=$liaison->pos->y + HAUTEUR_PERSONNE/2+HAUTEUR_GENERATION/2 - $num_mariage*ESPACEMENT_MARIAGES;

            // Point de liaison <-> Ligne des enfants
            $trait=new Trait(array('id'=>$id_pere, 'id2'=>$id_mere,
                                   'liaison'=>$liaison,
                                   'border'=>new Border(array('left'=>1)),
                                   'pos_debut'=>$liaison->pos,
                                   'height'=>$pos_trait_enfants_y - $liaison->pos->y,
                                   'name'=>'liaison_trait_enfants',
                                   'type'=>'conjoints')
                                );
            $trait->addOrUpdate();
            $traits[]=$trait;

            return $traits;
	}

        function lier_avec_enfants($conjoint,$num_mariage,$mariage,$fin_enfants_precedents) {

		$id_pere=$this->sexe=='H'?$this->id:$conjoint->id;
		$id_mere=$this->sexe!='H'?$this->id:$conjoint->id;
                $liaison=ComplexObjectToGet('Liaison', array('id'=>$id_pere,'id2'=>$id_mere));
                $pos_premier_enfant=ComplexObjectFieldToGet('Boite','pos',array('id'=>$mariage->enfants[0]));
		$pos_dernier_enfant=ComplexObjectFieldToGet('Boite','pos',array('id'=>$mariage->enfants[count($mariage->enfants)-1]));

		$pos_trait_enfants=new Coord(array('x'=>$pos_premier_enfant->x + LARGEUR_PERSONNE/2,
                                                   'y'=>$liaison->pos->y + HAUTEUR_PERSONNE/2+HAUTEUR_GENERATION/2 - $num_mariage*ESPACEMENT_MARIAGES));
                
                $trait=new Trait(array('id'=>$id_pere,'id2'=>$id_mere,
                                      'liaison'=>$liaison,
                                      'border'=>array('top'=>1),
                                      'pos_debut'=>$pos_trait_enfants,
                                      'width'=>$pos_dernier_enfant->x - $pos_premier_enfant->x - 4*LARGEUR_BORDURE,
                                      'name'=>'trait_enfants',
                                      'type'=>'conjoints'));
		$trait->addOrUpdate();
                $traits[]=$trait;
		return $traits;
        }

	function lier_avec_enfant($conjoint,$num_mariage,$enfant,$fin_enfants_precedents) { 
		$id_pere=$this->sexe=='H'?$this->id:$conjoint->id;
		$id_mere=$this->sexe!='H'?$this->id:$conjoint->id;
                
		debug('Liaison de '.$this->id.' avec son enfant '.$enfant->id.'<br />');
		$liaison=ComplexObjectToGet('Liaison', array('id'=>$id_pere, 'id2'=>$id_mere));
		$pos_trait_enfant=new Coord(array('x'=>$enfant->boite->pos->x + LARGEUR_PERSONNE/2,
						  'y'=>$liaison->pos->y + HAUTEUR_PERSONNE/2+HAUTEUR_GENERATION/2 - $num_mariage*ESPACEMENT_MARIAGES));
		
		if (!(ComplexObjectExists('Boite',array('id'=>$enfant->id))))
                    $pos_reelle=$enfant->boite->pos;
                else
                    $pos_reelle=ComplexObjectFieldToGet('Boite','pos',array('id'=>$enfant->id));
		// Ligne des enfants <-> Enfant
		$trait=new Trait(array('id'=>$id_pere, 'id2'=>$id_mere,'id3'=>$enfant->id,
							   'liaison'=>$liaison,
							   'border'=>new Border(array('left'=>1)),
							   'pos_debut'=>new Coord(array('x'=>$pos_reelle->x+LARGEUR_PERSONNE/2,'y'=>$pos_trait_enfant->y)),
							   'height'=>HAUTEUR_GENERATION/2-$num_mariage*ESPACEMENT_MARIAGES,
							   'name'=>'enfant type2',
							   'type'=>'ligne_enfants__enfant')
							);
		$trait->addOrUpdate();$traits[]=$trait;
		return array($trait);
	}
	
	function lier_avec_pere($pere, $liaison,$ids_enfants,$num_mariage) {
		$traits=array();
		debug('Liaison de '.$this->id.' avec son p�re '.$this->pere.'<br />');
		$debut_trait_enfants=new Coord(array('x'=>ComplexObjectFieldToGet('Boite','pos->x',array('id'=>$ids_enfants[0])),
                                                     'y'=>$liaison->pos->y+HAUTEUR_PERSONNE/2+HAUTEUR_GENERATION/2));
		
		$pos_trait_enfant=new Coord(array('x'=>$this->boite->pos->x + LARGEUR_PERSONNE/2,
										  'y'=>$debut_trait_enfants->y -$num_mariage*ESPACEMENT_MARIAGES));
		
		$nb_enfants=count($ids_enfants);
		$largeur_enfants=$nb_enfants*LARGEUR_PERSONNE+($nb_enfants==1?0:(($nb_enfants-1)*ESPACEMENT_ENFANT));
		// Point de liaison <-> Ligne des enfants
		$trait=new Trait(array('id'=>$this->pere,'id2'=>$this->mere,'id3'=>$this->id,
                                       'liaison'=>$liaison,
                                       'border'=>new Border(array('left'=>1)),
                                       'pos_debut'=>$liaison->pos,
                                       'height'=>$pos_trait_enfant->y-$liaison->pos->y,
                                       'name'=>'pere type1',
                                       'type'=>'point_liaison__ligne_enfants'));
		$trait->addOrUpdate();
                $traits[]=$trait;
		
		// Ligne des enfants <-> Enfant
		$trait=new Trait(array('id'=>$this->pere,'id2'=>$this->mere,'id3'=>$this->id,
                                       'liaison'=>$liaison,
                                       'border'=>new Border(array('left'=>1, 'top'=>0)),
                                       'pos_debut'=>$pos_trait_enfant,
                                       'height'=>HAUTEUR_GENERATION/2,
                                       'name'=>'pere type2',
                                       'type'=>'ligne_enfants__enfant'));
		$trait->addOrUpdate();
                $traits[]=$trait;
		
		// Ligne des enfants
		$trait=new Trait(array('id'=>$this->pere,'id2'=>$this->mere,'id3'=>$this->id,
                                       'liaison'=>$liaison,
                                       'border'=>array('top'=>1),
                                       'pos_debut'=>$debut_trait_enfants,
                                       'width'=>$largeur_enfants,
                                       'type'=>'conjoints',
                                       'name'=>'enfant type3'));
		$trait->addOrUpdate();
                $traits[]=$trait;
		return $traits;
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
							if ($boite2['left']>$trait2->pos_debut->x) { // Si la personne � �changer est d�plac�e de la gauche vers la droite
								if ($boite2['left'] < $trait->liaison->pos->x) { // Si cet enfant est � droite du point de liaison
									$trait2->pos_debut->x=$boite2['left']+LARGEUR_PERSONNE/2;
									$trait2->width-=(LARGEUR_PERSONNE+ESPACEMENT_EPOUX);
								}
								else {
									$trait2->pos_debut->x=$trait2->liaison->pos->x;
									$trait2->width=$boite2['left']+LARGEUR_PERSONNE/2-$trait2->liaison->pos->x;
								}
							}
							else {
								$trait2->width=LARGEUR_PERSONNE+ESPACEMENT_EPOUX;
							}
						break;
						case HAUTEUR_GENERATION/2: // Petit trait vertical reliant � l'enfant
							$trait2->pos_debut->x=$boite2['left']+LARGEUR_PERSONNE/2;
						break;
					}
				}
				if ($concernes[2] == $id2) {
					switch($trait->height) {
						case 0: // Trait horizontal entre les enfants
							if ($boite1['left']>$trait2->pos_debut->x) { // Si la personne � �changer est d�plac�e de la gauche vers la droite
								if ($boite1['left'] < $trait2->liaison->pos->x) { // Si cet enfant est � droite du point de liaison
									$trait2->pos_debut->x=$boite1['left']+LARGEUR_PERSONNE/2;
									$trait2->width-=(LARGEUR_PERSONNE+ESPACEMENT_EPOUX);
								}
								else {
									$trait2->pos_debut->x=$trait2->liaison->pos->x;
									$trait2->width=($boite1['left']+LARGEUR_PERSONNE/2)-$trait2->liaison->pos->x;
								}
							}
							else {
								$trait2->width+=(LARGEUR_PERSONNE+ESPACEMENT_EPOUX);
							}
						break;
						case HAUTEUR_GENERATION/2: // Petit trait vertical reliant � l'enfant
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
		$traits=ComplexObjectToGet('Trait', array('id3 IS NOT NULL'),'all');
		foreach($traits as $id_trait=>$trait) {
			$concernes=$trait->getConcernes();
			$parents=$concernes[0].'~'.$concernes[1];
			$id_enfant=$concernes[2];
			if (!strpos($trait['type'],'ligne_enfants__enfant')===FALSE) {
				if (!array_key_exists($parents,Personne::$liste_liaisons))
					continue;
				$point_liaison_parents=Personne::$liste_liaisons[$parents]->x;
				$boite_enfant=ComplexObjectToGet('Boite',array('id'=>$id_enfant));
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
			$trait_liaison_parents=ComplexObjectToGet('Trait', array('id'=>$parents[0],'id2'=>$parents[1],'id3'=>null));
			$liaison_parents=$trait_liaison->parents->liaison;
			$trait=new Trait(array('id'=>$parents[0],'id2'=>$parents[1],
								 'liaison'=>$liaison_parents,
								 'border'=>new Border(array('top'=>1)),
								 'pos_debut'=>new Coord(array('x'=>$gauche,'y'=>$liaison_parents->y+HAUTEUR_PERSONNE/2+HAUTEUR_GENERATION/2)),
								 'width'=>$droite-$gauche,
								 'type'=>'conjoints',
								 'name'=>'enfant type3'));
			$trait->add();
			Personne::$retour['traits']['creation'][]=$trait;
		}
		
	}
	
	static function corriger_lignes_horizontales() {
		$debut=microtime(true); 
		$tops_traits=array();
		$type2_concernes=array();
		$traits=ComplexObjectToGet('Trait',array(),'all');
		foreach($traits as $id_trait=>$trait) {
			$concernes=implode('~',$trait->getConcernes());
			if (!is_null($trait->height) && $trait->height!=0) {
				$type2_concernes[$concernes][$id_trait]=strpos($trait->name,'type2')!=FALSE;
			}
			else {
				if (!array_key_exists($trait->pos_debut->y,$tops_traits))
					$tops_traits[$trait->pos_debut->y]=array($id_trait=>$trait);
				$tops_traits[$trait->pos_debut->y][]=$trait;
			}
		}
		
		foreach($tops_traits as $top=>$traits) {
			usort($traits,array('Personne','trier_lefts'));
			$marges_gauches=array();
			$parents_places=array();
			foreach($traits as $id_trait=>$trait) {
				$tab_concernes=$trait->getConcernes();
				$concernes=explode('~',$tab_concernes);
				$parents=array($concernes[0],$concernes[1]); 
				if (in_array($parents,$parents_places))
					break;
				$trouve=-1;
				for ($i=0;$i<((HAUTEUR_GENERATION/2)/ESPACEMENT_MARIAGES)-2;$i++) {
					if (!isset($marges_gauches[$i]) || $marges_gauches[$i]['marge']<$trait->pos_debut->x) {
						$trouve=$i;break;
					}
				}
				if ($trouve<=0)
					$parents_places[]=$parents;
					
				if ($trouve!=-1 && $trouve!=0) {
					$traits_couple=Trait::getTraitsCouple($tab_concernes[0],$tab_concernes[1]);
					$traits_couple[$concernes]->pos_debut->y=$trait->pos_debut->y+$trouve*ESPACEMENT_MARIAGES;
					$marges_gauches[$trouve]=array('parents'=>$parents,
											 	   'marge'=>$trait->pos_debut->x+$trait->width);
					
					foreach($type2_concernes as $concernes=>$traits) {
				  		$tab_concernes=explode('~',$concernes);
						if (($tab_concernes[0]==$parents[0] && $tab_concernes[1]==$parents[1])
					  	  ||($tab_concernes[0]==$parents[1] && $tab_concernes[1]==$parents[0])) {
							foreach($traits as $id_trait=>$est_type2) { // On d�cale les autres traits
								if ($est_type2) { // Trait des enfants <-> Trait de l'enfant
							  		$trait->pos_debut->y+=$trouve*ESPACEMENT_MARIAGES;
							  		$trait->pos_debut->height-=$trouve*ESPACEMENT_MARIAGES;
						  		}
						  		else // Trait de liaison <-> Trait des enfants
						  			$trait->pos_debut->height+=$trouve*ESPACEMENT_MARIAGES;
						  	}
					  	}
					}
				}
				elseif ($trouve==0) {
					$marges_gauches[$trouve]=array('parents'=>$parents,
											 	   'marge'=>$trait->pos_debut->x+$trait->width);
				}
				else {
					
					echo 'Probl�me de placement pour '.$parents[0].'-'.$parents[1]
						.'Pensez � augmenter le param�tre HAUTEUR_GENERATION ou � diminuer ESPACEMENT_MARIAGES.<br />';
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
		$boites=ComplexObjectToGet('Boite',array(),'all');
		foreach($boites as $boite) {
			if ($boite->pos->x < 0 && -1*$boite->pos->x > $correction_gauche)
				$correction_gauche=2 + -1*$boite->pos->x;
			if ($boite->pos->y < 0 && -1*$boite->pos->y > $correction_haut)
				$correction_haut=  2 + -1*$boite->pos->y;
		}
	
		$traits=ComplexObjectToGet('Trait',array(),'all');
		foreach($traits as $trait) {
			if ($trait->pos_debut->x < 0 && -1*$trait->pos_debut->x > $correction_gauche)
				$correction_gauche=2 + -1*$trait->pos_debut->x;
			if ($trait->pos_debut->y < 0 && -1*$trait->pos_debut->y > $correction_haut)
				$correction_haut=  2 + -1*$trait->pos_debut->y;
		}
		
		foreach($boites as $boite) {
			$boite->pos->incr($correction_gauche,$correction_haut);
			$boite->update();
		}
		
		foreach($traits as $id_trait=>$trait) {
			$trait->pos_debut->incr($correction_gauche,$correction_haut);
			$trait->update();
		}
		Personne::$retour['decalage']=array('left'=>$correction_gauche,'top'=>$correction_haut);
		
		
		debug('D�calage de '.$correction_gauche.' vers la droite et '.$correction_haut.' vers le bas<br />');
	}
	
	static function date_to_year($date) {
		$pos_dernier_slash=strrpos($date,'/');
		return substr($date,1+$pos_dernier_slash,strlen($date)-$pos_dernier_slash);
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
	
        static function ajouter_a_retour($element, $type_action, $nouveau_tableau) {
            Personne::$retour[$element.'s'][$type_action] = array_merge(Personne::$retour[$element.'s'][$type_action],$nouveau_tableau);
        }
}

function debug($texte) {
    if (DEBUG) {
        for($i=0;$i<count(debug_backtrace())-1;$i++)
            echo '&nbsp;';
        echo $texte;
    }
}

Personne::$note_supplementaire='Source : Arbre g�n�alogique Geneanet de Jean-Marcel Boidier';

if (isset($_POST['analyse'])) {
	Personne::$id_session=$_POST['id_session'];
        Personne::$site_source=$_POST['site_source'];
        $autres_args=str_replace(';pcnt;','%',$_POST['autres_args']);
        Personne::$id_depart=$autres_args;
	$niveau=new Level();
	$niveau->niveau_courant=0;
	$niveau->addOrUpdate();
	$level_courant=ComplexObjectToGet('Level');
	Personne::$niveau_courant=$level_courant->niveau_courant;
        switch(Personne::$site_source) {
            case 'Geneanet' :
               Geneanet::$nom_domaine='http://'.$_POST['serveur'].'.geneanet.org/';
               $url=Geneanet::$nom_domaine.'index.php3?b='.$_POST['pseudo'].'&lang=fr;'.$autres_args;
               break;
        }
	$p = new Personne($url);
	$level_courant->niveau_courant=Personne::$niveau_courant;
	$resultat_analyse=$p->analyser();
         echo json_encode($resultat_analyse);
        //header("X-JSON: " . json_encode($resultat_analyse));
}
elseif (isset($_POST['make_tree'])) {
	Personne::$id_session=$_POST['id_session']; 
	Personne::$niveau_courant=$level_courant->niveau_courant;
	$url='http://'.$_POST['serveur'].'.geneanet.org/index.php3?b='.$_POST['pseudo'].'&lang=fr;'.str_replace(';pcnt;','%',$_POST['autres_args']);
	$url=urldecode($url);
	$p = new Personne($url);
	$level_courant->niveau_courant=Personne::$niveau_courant;
	header("X-JSON: " . json_encode($p->analyser(true)));
}

?>