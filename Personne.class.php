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
					     .'(?:(?:(?!, dont).)*, dont[^<]*<ul>[^<]*(?:'.Personne::$ligne_personne_classique2.'(?:(?:(?!</li>).)*)</li>[^<]*)+</ul>)*[^<]*</li>[^<]*)+</ul>)#isuU';
Personne::$regex_mariages_conjoints='#<li style="vertical\-align: middle;list\-style\-type: (circle|disc|square)">Mari.e? ?((?:<em>[^<]+</em>)?)[^a]*avec <a href="([^"]+)">([^<]+)</a>(?: <em><bdo dir="ltr">[^<]*</bdo></em>)?'
			   					   .'((?:(?:(?!, dont).)*, dont[^<]*<ul>[^<]*(?:'.Personne::$ligne_personne_classique2.'(?:(?:(?!</li>).)*)</li>[^<]*)+</ul>)?)[^<]*</li>#isu';
Personne::$regex_mariages_enfants='#'.Personne::$ligne_personne_classique.'#isu';
Personne::$regex_patronyme='#<img src="http://images.geneanet\.org/v3/pictos_geneweb/[^/]+/(?:(?:saisie-(?:homme|femme))|sexeinconnu)\.gif" alt="(H|F|\?)" title="(?:H|F|\?)" />'
						  .'</td>[^<]*<td class="highlight2">&nbsp;(?:(?:[^<]*<a href="[^"]*">([^<]+)</a>[^<]*<a href="[^"]*">([^<]+)</a>)|..([^<]*)</td>)#isu';

class Personne {
	static $retour;
	
	// Construction graphique de l'arbre
	static $liste_pos=array();
	static $liste_mariages_dessines=array();
	static $liste_liaisons=array();
	static $liste_boites=array();
	static $liste_personnes_decalees=array();
	static $liste_marges_gauches=array();
	
	static $niveau_courant;
        static $id_depart;
	static $id_session;
	static $liste_familles=array();
	static $ids_parcourus=array();
	static $ids_en_cours=array();
	static $personnes_ecrites=array();
	static $personnes_en_cours=array();
	static $ligne_personne_classique='<li style="vertical\-align: middle;list\-style\-type: (circle|disc|square);?">(?:<img[^>]*> )?<a href="([^"]+)">([^<]+)</a>';
	static $ligne_personne_classique2='<li style="vertical\-align: middle;list\-style\-type: (?:circle|disc|square);?">(?:<img[^>]*> )?<a href="[^"]+">[^<]+</a>';
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
	var $mariages=array();
	
	var $boite;
	
	static function setIdSession($id_session) {
		self::$id_session=$id_session;
	}
	
	static function getIdSession() {
		return self::$id_session;
	}
	
	function Personne($url,$sexe='I', $naissance='',$mort='',$autres='',$prenom='',$nom='',$pere='',$mere='') {
		$this->url=$url;$this->naissance=$naissance;$this->mort=$mort;$this->autres=$autres;
		$this->prenom=$prenom;$this->nom=$nom;
		$this->pere=$pere;
		$this->mere=$mere;
		$this->id=$this->to_id();
	}
	
	function analyser($from_bd=false) {
		if (LIMITE_PROFONDEUR_SNIFFER!=0 && count(debug_backtrace())>LIMITE_PROFONDEUR_SNIFFER) {
			echo '[TMR]<br />';
			return;
		}
		$retour=array();
		Personne::$retour['boites']=array('creation'=>array(),'modif'=>array());
		Personne::$retour['traits']=array('creation'=>array(),'modif'=>array());
		$this->from_bd();
		
		$ch = curl_init($this->url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
		$page = curl_exec($ch);
		curl_close($ch);
                /*
                $handle = fopen($this->url, "rb");
                $page = stream_get_contents($handle);
                fclose($handle);*/
                if (is_null($page) || $page===false) {
                    fatal_error('Connection problem with '.$this->url.', exiting...');
                }
		if ($this->estPremiereAnalyse())
			Personne::$niveau_courant=0;
		else {
			// Récupérer la relation avec la personne appelante
			$id_personne_appelante=$_POST['caller'];
			$personne_appelante=new Personne(str_replace(';pcnt;','%',$id_personne_appelante));
			$personne_appelante->from_bd();
			$personne_appelante->boite->fixNiveauCourant();
			Personne::$niveau_courant+=$this->getDifferenceNiveauAvec($id_personne_appelante);
		}
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
		$this->naissance=$naissance;
		$this->mort=$mort;
		$this->autres=$autres;
		
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
	
		if (!isset($sexe))
			$this->sexe='I';
		else
			$this->sexe=$sexe;
			
		if (is_null(ComplexObjectToGet('Boite',array('id'=>$this->id)))) {
                    Personne::$retour['boites']['creation'][]=$this->dessiner();
                    if (is_null($this->boite))
                            echo 'Boite non créée';
                }
		else {
			$this->genererBoite(ComplexObjectToGet('Boite',array('id'=>$this->id))->pos);
			Personne::$retour['boites']['modif'][]=$this->boite;
		}
		$this->boite->addOrUpdate();
		preg_match(Personne::$regex_mariages,$page,$r_mariages);
                //echo 'coucou';exit(0);
		if (isset($r_mariages[1])) {
                    preg_match_all(Personne::$regex_mariages_conjoints,$r_mariages[1],$r_mariages_conjoints);

                    $fin_enfants_precedents=0;
                    for($i=0;$i<count($r_mariages_conjoints[0]);$i++) {
                        $url_conjoint=Personne::$nom_domaine.$r_mariages_conjoints[3][$i];
                        if (!empty($r_mariages_conjoints[2][$i])) { // Détails mariage
                            preg_match(Personne::$regex_mariages_conjoints_details,$r_mariages_conjoints[2][$i],$r_detail_mariage);
                            list($date_mariage,$lieu_mariage)=decomposer_naissance_mort($r_detail_mariage[1].$r_detail_mariage[2]);
                        }
                        else
                            $date_mariage=$lieu_mariage='';
                        $enfants=array();
                        if (!empty($r_mariages_conjoints[5][$i])) { // Enfants
                            preg_match_all(Personne::$regex_mariages_enfants,$r_mariages_conjoints[5][$i],$r_enfants);
                            for($j=0;$j<count($r_enfants[0]);$j++) {
                                $url_enfant=Personne::$nom_domaine.$r_enfants[2][$j];
                                array_push($enfants,new Personne($url_enfant,'I','?','?','',Personne::url_to_id($url_enfant),'...',null,null));
                            }
                        }
                        $id_conjoint=Personne::url_to_id($url_conjoint);
                        $conjoint_existe_bd=Personne::existe_en_bd($id_conjoint);
                        $conjoint=new Personne($id_conjoint,'I','?','?','',$id_conjoint,'...',null,null);
                        if ($conjoint_existe_bd)
                            $conjoint->from_bd();
                        list($id_homme,$id_femme)=Personne::toHomme_Femme($this,$conjoint);
                        $conjoint->sexe=($id_conjoint==$id_homme)?'H':'F';
                        if ($conjoint_existe_bd) {
                            $liaison=ComplexObjectFieldToGet('Trait','liaison',array('id'=>$id_homme,'id2'=>$id_femme,'type'=>'conjoints'));
                            $pos_conjoint=ComplexObjectFieldToGet('Boite','pos',array('id'=>$id_conjoint));
                            $action_conjoints='modif';
                        }
                        else {
                            $pos_conjoint=new Coord(array('x'=>$this->boite->pos->x,'y'=>$this->boite->pos->y));
                            $pos_conjoint->x+=($conjoint->sexe=='F' ? 1 : -1)*(LARGEUR_PERSONNE+ESPACEMENT_EPOUX+LARGEUR_BORDURE*4);
                            $action_conjoints='creation';
                        }

                        Personne::$retour['boites'][$action_conjoints][]=$conjoint->dessiner($pos_conjoint);
                        $homme=$this->sexe=='H' ? $this : $conjoint;
                        $femme=$this->sexe=='H' ? $conjoint : $this;
                        //if (!$conjoint_existe_bd) {
                        $liaison=Personne::calculerLiaison($homme,$femme,$i, $fin_enfants_precedents);
                        $liaison->addOrUpdate();
                        //}
                        //$liaison=ComplexObjectToGet('Liaison', array('id'=>$homme->id,'id2'=>$femme->id));
                        Personne::$retour['mariages'][$i]['conjoint']=array('id'=>$id_conjoint,
                                                                            'action'=>$conjoint_existe_bd ? 'already_done' : 'todo');
                        $mariage=new Mariage(array('id'=>'','conjoint1'=>$id_homme,'conjoint2'=>$id_femme,'date_mariage'=>$date_mariage,'lieu_mariage'=>$lieu_mariage));

                        $mariage->addOrUpdate();
                        $mariage=ComplexObjectToGet('Mariage',array('conjoint1'=>$id_homme,'conjoint2'=>$id_femme)); // Pour récupérer l'ID

                        Personne::ajouter_a_retour('trait', $action_conjoints, $this->lier_avec_conjoint($conjoint,$i, $date_mariage));
                        if (count($enfants) > 0) {
                            Personne::ajouter_a_retour('trait', $action_conjoints, $this->lier_avec_trait_enfants($conjoint,$i,$mariage,$fin_enfants_precedents));
                            $liaison=ComplexObjectToGet('Liaison', array('id'=>$id_homme,'id2'=>$id_femme));
                            $largeur_enfants=LARGEUR_PERSONNE*count($enfants) + ESPACEMENT_ENFANT*(count($enfants)-1);
                            Personne::$niveau_courant++;
                            foreach($enfants as $j=>$enfant) {
                                $pos_enfant=new Coord(array('x'=>($fin_enfants_precedents>0?($fin_enfants_precedents):($liaison->pos->x-$largeur_enfants/2)) + $j*(LARGEUR_PERSONNE+ESPACEMENT_ENFANT),
                                                            'y'=>$this->boite->pos->y+HAUTEUR_PERSONNE+HAUTEUR_GENERATION));
                                $enfant_existe_en_bd=Personne::existe_en_bd($enfant->id);
                                $action_enfant=$enfant_existe_en_bd?'modif':'creation';
                                Personne::$retour['boites'][$action_enfant][]=$enfant->dessiner($pos_enfant);
                                Personne::ajouter_a_retour('trait', $action_enfant, $this->lier_avec_enfant($conjoint,$i,$enfant,$conjoint->boite->pos->x));
                                if ($action_enfant=='creation')
                                    $enfant->to_bd(); // Puis on ajoute l'enfant en tant que Personne...
                                Personne::$retour['mariages'][$i]['enfants'][$j]=array('id'=>$enfant->id,'action'=>$action_enfant=='creation'?'todo':'already_done');
                                if (is_null(ComplexObjectToGet('EnfantMariage',array('id_enfant'=>$enfant->id, 'id_mariage'=>$mariage->id)))) {
                                    $o_enfant=new EnfantMariage(array('id_enfant'=>$enfant->id, 'id_mariage'=>$mariage->id));
                                    $o_enfant->add(); // ... Et l'enfant en tant que relation avec ses parents
                                }
                                $mariage->enfants[]=$enfant->id;
                            }
                            if (count($enfants) > 0)
                                Personne::ajouter_a_retour('trait', $action_conjoints, $this->lier_avec_enfants($conjoint,$i,$mariage,$fin_enfants_precedents));
                            Personne::$niveau_courant--;
                            $mariage->update();
                        }
                        $this->mariages[$i]=$mariage;

                        $famille_existante=false;
                        foreach(Personne::$liste_familles as $famille) {
                            if ($famille_existante) break;
                            if (($famille->conjoint1==$this->id && $famille->conjoint2==$url_conjoint)
                                    ||($famille->conjoint1==$url_conjoint && $famille->conjoint2==$this->id))
                                $famille_existante=true;
                        }
                        if (!$famille_existante)
                            array_push(Personne::$liste_familles,$mariage);

                        if (count($enfants)>0) {
                            $dernier_enfant=$enfants[count($r_enfants[0])-1];
                            $fin_enfants_precedents= $dernier_enfant->boite->pos->x + LARGEUR_PERSONNE+ESPACEMENT_INCONNUS;
                        }
                        $conjoint->to_bd();
                    }
                }
		Personne::$niveau_courant--;
		if ($parents==1) {
                    $url_parents=array('pere'=>Personne::$nom_domaine.$r_parents[2],'mere'=>Personne::$nom_domaine.$r_parents[5]);
                    $this->pere=Personne::url_to_id($url_parents['pere']);
                    $this->mere=Personne::url_to_id($url_parents['mere']);
                    $pere=new Personne($url_parents['pere'],'H','?','?','',$this->pere,'?',null,null);
                    $liste_parents=array('pere','mere');
                    foreach($liste_parents as $parent) {
                        if (!is_null($this->$parent)) {
                            Personne::$retour[$parent]=array('id'=>$this->$parent,
                                                             'action'=>Personne::verifier_peut_parcourir($this->$parent)?'todo':'already_done');
                            $boite_parent=ComplexObjectToGet('Boite',array('id'=>$this->$parent));

                            if (!is_null($boite_parent)/* && $o_parent->getEtat()=='make_tree'*/) { // Le parent est dans la base de données => On a toutes les informations sur lui
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
                                    //Vérifier que ces traits ne sont pas déjà dessinés
                                    if (!is_null(ComplexObjectToGet('Trait',array('id'=>$this->pere, 'id2'=>$this->mere, 'id3'=>$this->id))) && $parent=='pere') {
                                        $liaison=new Liaison(array('id'=>$this->pere, 'id2'=>$this->mere,'pos'=>$coords_liaison));
                                        $liaison->update();
                                        Personne::ajouter_a_retour('trait', 'modif', $this->lier_avec_pere($pere,$liaison,$ids_enfants,$numero_mariage));
                                    }
                                }
                                else
                                    echo 'Mariage de '.$this->pere.' ayant donné '.$this->id.' non trouvé !';
                            }
                            else { // Le père n'a pas encore été parcouru => On fait avec les infos qu'on a
                                /*$this->from_bd();
						$champs_utf8=array('prenom','nom','autres','naissance','mort','lieu_naissance','lieu_mort');
						foreach($champs_utf8 as $champ)
							$this->$champ=utf8_decode($this->$champ);*/
                                $nb_enfants_parent=1;
                                $numero_enfant_fratrie=0;
                                $largeur_enfants=LARGEUR_PERSONNE;

                                $coord_liaison_parents
                                        =new Coord(array('x'=>$this->boite->pos->x + $largeur_enfants/2,
                                                         'y'=>$this->boite->pos->y-HAUTEUR_GENERATION-HAUTEUR_PERSONNE/2));

                                $o_parent=new Personne($url_parents[$parent],'I','?','?','','?',null,null);
                                $o_parent->prenom=$o_parent->id;
                                $o_parent->nom='...';
                                $o_parent->sexe=($parent=='pere')?'H':'F';
                                $pos_boite=new Coord(array('x'=>$coord_liaison_parents->x+($parent=='pere'?(-1*(ESPACEMENT_EPOUX/2+LARGEUR_PERSONNE)):ESPACEMENT_EPOUX/2),
                                                           'y'=>$coord_liaison_parents->y-HAUTEUR_PERSONNE/2));
                                Personne::$retour['boites']['creation'][]=$o_parent->dessiner($pos_boite);

                                $o_parent->to_bd();
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
		
		$this->delete_from_bd();
		$this->to_bd();
		$this->setEtat('make_tree');
		//$marge->init();
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
		echo 'Aucune relation trouvée entre '.$id_personne.' (appelant) et '.$this->id;
		exit(0);
	}
	
	function estPremiereAnalyse() {
		$requete_personnes='SELECT Count(id) AS cpt FROM personnes WHERE id_session='.Personne::$id_session;
		$resultat_requete_personnes=Requete::query($requete_personnes);
		if ($info_nb_personnes=mysql_fetch_array($resultat_requete_personnes))
			return $info_nb_personnes['cpt'] ==0 ;
	}
	
	static function toHomme_Femme(Personne $p1, Personne $p2) {
		$homme_id=$p1->sexe=='H'?$p1->id:$p2->id;
		$femme_id=$p1->sexe!='H'?$p1->id:$p2->id;
		
		return array($homme_id,$femme_id);
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
						$this->boite->pos=new Coord(array('x'=>$value->x,'y'=>$value->y));
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
						$this->boite->pos->y=$value;
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
						$this->boite->pos->x=$value;
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
	function delete_from_bd() {
		$requete='DELETE FROM personnes WHERE id LIKE \''.$this->id.'\' AND id_session='.Personne::$id_session;
		Requete::query($requete);
	}
	
	
	function to_bd() {
		$champs_a_transcrire=array('id','naissance','date_naissance','lieu_naissance','date_mort','lieu_mort','mort','autres','prenom','nom','sexe','pere','mere');
		
		$requete='INSERT INTO personnes (`id_session`';
		foreach($champs_a_transcrire as $champ)
			$requete.=', `'.$champ.'`';
		$requete.=',`etat`) ';
		$requete.='VALUES ('.Personne::$id_session;
		foreach($champs_a_transcrire as $champ) {
			if (!is_null($this->$champ) && !empty($this->$champ))
				$requete.=', \''.addslashes($this->$champ).'\'';
			else	
				$requete.=', NULL';
		}
		$requete.=', \'En cours\');';
		Requete::query($requete) or die (mysql_error()); 
		if (!$this->boite) {
			echo 'Aucune boite définie pour ajouter '.$this->id;
			print_r(debug_print_backtrace());
			exit(0);
		}
		$this->boite->addOrUpdate();
	}
	
	function from_bd() {
		$champs_a_recuperer=array('naissance','date_naissance','lieu_naissance','date_mort','lieu_mort','mort','autres','prenom','nom','sexe','pere','mere');
		$requete='SELECT id';
		foreach($champs_a_recuperer as $champ)
			$requete.=', `'.$champ.'`';
		$requete.=' FROM personnes WHERE id LIKE \''.$this->id.'\' AND id_session='.Personne::$id_session;
		$requete_resultat=Requete::query($requete) or die (mysql_error());
		if ($infos=mysql_fetch_array($requete_resultat)) {
			foreach($infos as $champ=>$valeur)
				$this->$champ=$valeur;
		}
		else return null;
		$this->boite=ComplexObjectToGet('Boite',array('id'=>$this->id));
		if (is_null($this->boite))
			echo 'Aucune boite définie pour la récupération de '.$this->id;
		
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
	
	/*function dessinerArbre($priorite) {
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
				
				$conjoint->change_bd(array('pos'=>new Coord(array('x'=>-1,'y'=>$this->boite->pos->y))));
				if (array_key_exists(Personne::$niveau_courant+1,Personne::$liste_marges_gauches)
				   && $conjoint->boite->pos->x < Personne::$liste_marges_gauches[Personne::$niveau_courant+1] 
				   && $this->boite->pos->x>0 && count($mariage->enfants)>0) {
					$conjoint->change_bd(array('pos_x'=>Personne::$liste_marges_gauches[Personne::$niveau_courant+1]+ESPACEMENT_INCONNUS));
				}
				else {
					$conjoint->change_bd(array('pos_x'=>$this->boite->pos->x+LARGEUR_PERSONNE+ESPACEMENT_EPOUX+((LARGEUR_PERSONNE+70)*$num_mariage)));
				}
				$this->lier_avec_conjoint($conjoint,$num_mariage,$mariage->date_mariage);
				$liaison=new Coord(array('x'=>(($conjoint->boite->pos->x+LARGEUR_PERSONNE)-$this->boite->pos->x)/2,'y'=>$this->boite->pos->y));
				$nb_enfants=count($mariage->enfants);
				$largeur_enfants=$nb_enfants*LARGEUR_PERSONNE+($nb_enfants==1?0:(($nb_enfants-1)*ESPACEMENT_ENFANT));
				$conjoint->dessinerArbre(0);
				Personne::$niveau_courant++;;
				foreach($mariage->enfants as $num_enfant=>$id_enfant) {
					$enfant=PersonneFromBD($id_enfant);
					$enfant->change_bd(array('pos'=>
						new Coord(array('x'=>($fin_enfants_precedents>0?($fin_enfants_precedents):($liaison->pos->x-$largeur_enfants/2)) + $num_enfant*(LARGEUR_PERSONNE+ESPACEMENT_ENFANT),
								   		'y'=>$this->boite->pos->y+HAUTEUR_PERSONNE+HAUTEUR_GENERATION))));
					$enfant->dessinerArbre(1);
					$this->lier_avec_enfant($conjoint,$num_mariage,$enfant,$conjoint->boite->pos->x);
				}
				Personne::$niveau_courant--;;
				if ($nb_enfants>0) {
					$dernier_enfant=PersonneFromBD($this->mariages[$num_mariage]->enfants[$nb_enfants-1]);
					$fin_enfants_precedents= $dernier_enfant->boite->pos->x + LARGEUR_PERSONNE+ESPACEMENT_INCONNUS;
				}
			}
		}
			
		if ($this->pere!=null) {
			$pere=PersonneFromBD($this->pere);
			if ($pere->mariages!=null) {
				Personne::$niveau_courant--;;
				foreach($pere->mariages as $num_mariage=>$mariage)
					foreach($mariage->enfants as $num_enfant=>$id_enfant)
						if ($id_enfant==$this->id)
							list($numero_mariage,$num_enfant)=array($num_mariage,$num_enfant);
				if (isset($numero_mariage)) {
					$nb_enfants=count($pere->mariages[$numero_mariage]->enfants);
					$largeur_enfants=$nb_enfants*LARGEUR_PERSONNE+($nb_enfants==1?0:(($nb_enfants-1)*ESPACEMENT_ENFANT));
					$liaison=new Coord(array('x'=>$this->boite->pos->x + $largeur_enfants/2 - $num_enfant*(LARGEUR_PERSONNE+ESPACEMENT_ENFANT),
												 'y'=>$this->boite->pos->y-HAUTEUR_GENERATION-HAUTEUR_PERSONNE/2));
					$pere->boite->pos
						=new Coord(array('x'=>$liaison->pos->x-ESPACEMENT_EPOUX/2-LARGEUR_PERSONNE,
										 'y'=>$liaison->pos->y-HAUTEUR_PERSONNE/2));
					if ($pere->dessinerArbre(1)) {
						//Vérifier que ces traits ne sont pas déjà dessinés
						if (!(Trait::traitEnfantExiste($this->pere, $this->mere, $this->id)))
							$this->lier_avec_pere($pere,$liaison,$numero_mariage);
					}
				}
				Personne::$niveau_courant++;
			}
		}
		
		
		unset(Personne::$ids_en_cours[$this->id]);
		return true;
	}*/

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
	
	function dessiner($pos_boite=null) {
		debug('<br />');
		debug('Création de '.$this->prenom.' '.$this->nom.'<br />');
		$marge_gauche= ComplexObjectToGet('Marge',array('niveau'=>Personne::$niveau_courant));
		if (!$marge_gauche) {
			echo 'Marge gauche creee';
			$marge_gauche=new Marge(array('niveau'=>Personne::$niveau_courant,'marge'=>0));
			$marge_gauche->add();
		}
		if (is_null($pos_boite)) {
			$marge_boite=new Coord(array('x'=>$marge_gauche->marge,
										 'y'=>Personne::$niveau_courant*(HAUTEUR_PERSONNE+HAUTEUR_GENERATION)));
			
			if ($marge_boite->x>0) {
				debug($this->prenom.' '.$this->nom
					 .' ==> '.$marge_boite->x+LARGEUR_BORDURE*4+ESPACEMENT_INCONNUS.', au lieu de '.$marge_boite->x.'<br />');
				$marge_boite->x+=LARGEUR_BORDURE*4+ESPACEMENT_INCONNUS;
			}
		}
		else
			$marge_boite=$pos_boite;
		$this->genererBoite($marge_boite);
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
                                       'name'=>'conjoint',
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
                                   'name'=>'enfant type1',
                                   'type'=>'point_liaison__ligne_enfants')
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
                                      'type'=>'conjoints',
                                      'name'=>'enfant type3'));
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
		
		if (array_key_exists($enfant->id,Personne::$liste_boites))
			$pos_reelle=ComplexObjectFieldToGet('Boite','pos',array('id'=>$enfant->id));
		else
			$pos_reelle=$enfant->boite->pos;
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
		debug('Liaison de '.$this->id.' avec son père '.$this->pere.'<br />');
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
		$trait->addOrUpdate();$traits[]=$trait;
		
		// Ligne des enfants <-> Enfant
		$trait=new Trait(array('id'=>$this->pere,'id2'=>$this->mere,'id3'=>$this->id,
                                       'liaison'=>$liaison,
                                       'border'=>new Border(array('left'=>1, 'top'=>0)),
                                       'pos_debut'=>$pos_trait_enfant,
                                       'height'=>HAUTEUR_GENERATION/2,
                                       'name'=>'pere type2',
                                       'type'=>'ligne_enfants__enfant'));
		$trait->addOrUpdate();$traits[]=$trait;
		
		// Ligne des enfants
		$trait=new Trait(array('id'=>$this->pere,'id2'=>$this->mere,'id3'=>$this->id,
                                       'liaison'=>$liaison,
                                       'border'=>array('top'=>1),
                                       'pos_debut'=>$debut_trait_enfants,
                                       'width'=>$largeur_enfants,
                                       'type'=>'conjoints',
                                       'name'=>'enfant type3'));
		
		$debut_trait=(($liaison->pos->x>$pos_trait_enfant->x)?$pos_trait_enfant:$liaison->pos);
		return $traits;
	}
	
	static function existe_en_bd($id) {
		$requete='SELECT id FROM personnes WHERE id LIKE \''.$id.'\' AND id_session='.Personne::$id_session;
		$requete_resultat=Requete::query($requete);
		$existe=mysql_num_rows($requete_resultat) > 0;
		if ($existe) echo $id.' est déjà dans la base de données<br />';
		return $existe;
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
								if ($boite2['left'] < $trait->liaison->pos->x) { // Si cet enfant est à droite du point de liaison
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
						case HAUTEUR_GENERATION/2: // Petit trait vertical reliant à l'enfant
							$trait2->pos_debut->x=$boite2['left']+LARGEUR_PERSONNE/2;
						break;
					}
				}
				if ($concernes[2] == $id2) {
					switch($trait->height) {
						case 0: // Trait horizontal entre les enfants
							if ($boite1['left']>$trait2->pos_debut->x) { // Si la personne à échanger est déplacée de la gauche vers la droite
								if ($boite1['left'] < $trait2->liaison->pos->x) { // Si cet enfant est à droite du point de liaison
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
							foreach($traits as $id_trait=>$est_type2) { // On décale les autres traits
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
	
	static function date_to_year($date) {
		$pos_dernier_slash=strrpos($date,'/');
		return substr($date,1+$pos_dernier_slash,strlen($date)-$pos_dernier_slash);
	}
	
	static function url_to_id($url) {
		$regex='#&lang=fr;*(?:pz=[^;]+;)?(?:nz=[^;]+;)?(?:ocz=[^;]+;)?(.*)#is';
		preg_match($regex,$url,$resultat);
		if (count($resultat)==2)
			return $resultat[1];
		else
			return $url;
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
        $autres_args=str_replace(';pcnt;','%',$_POST['autres_args']);
        Personne::$id_depart=$autres_args;
	$niveau=new Level();
	$niveau->niveau_courant=0;
	$niveau->addOrUpdate();
	$level_courant=ComplexObjectToGet('Level');
	Personne::$niveau_courant=$level_courant->niveau_courant;
	$url='http://'.$_POST['serveur'].'.geneanet.org/index.php3?b='.$_POST['pseudo'].'&lang=fr;'.$autres_args;
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