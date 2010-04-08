<?php 
include_once('Personne.class.php');
include_once('Mariage.class.php');
$args=isset($_GET['args']) ? $_GET['args'] : (isset ($_POST['args']) ? $_POST['args'] : null);
if (isset($args)) {
	$serveur=$args['serveur'];
	$pseudo=$args['pseudo'];
	$autres_args=$args['args']; // Autre à faire : http://gw4.geneanet.org/index.php3?b=micheldunoyer&lang=fr;p=joseph;n=ritz
	
}
else {
	$serveur='gw0';
	$pseudo='astrofifi';
	$id='p=louis+raymond+henry;n=chevallereau;oc=0';
}
Personne::$nom_domaine='http://'.$serveur.'.geneanet.org/';
$url='http://'.$serveur.'.geneanet.org/index.php3?b='.$pseudo.'&lang=fr;'.$id;

global $personne_source;

$personne_source=new Personne($url);
Personne::initMake_tree();
?>
 <!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.1//EN" "http://www.w3.org/TR/xhtml11/DTD/xhtml11.dtd"> 
<html>
<head>
	<title>GedcomMaker</title>
	<meta http-equiv="content-type" content="text/html; charset=iso-8859-1" />

	<!-- jsProgressBarHandler prerequisites : prototype.js -->
	<script type="text/javascript" src="js/prototype/prototype.js"></script>

	<!-- jsProgressBarHandler core -->
	<script type="text/javascript" src="js/bramus/jsProgressBarHandler.js"></script>
	<script type="text/javascript">
		var pile_personnes=new Array(); 
		var serveur_g='<?=$serveur?>';
		var pseudo_g='<?=$pseudo?>';
		var id_session_g=1270639965;
		var id_source='<?=$personne_source->id?>';
		pile_personnes.push(id_source);
		var script='make_tree';
	</script>
	<script type="text/javascript" src="js/gedcommaker.js"></script>
	<link rel="stylesheet" media="screen" href="progressbar.css" />
	<link rel="stylesheet" media="screen" href="style.css" />
</head>
<body id="body">
<a href="javascript:void(0)" onclick="routine()">Commencer</a><br />
<span style="color:#006600;font-weight:bold;"><?=$personne_source->id?></span> <br/>
<span class="progressBar" name="niveau0" id="<?=$personne_source->id?>"></span>
</body>
</html>
<?php exit(0);?>

<?php
include_once('Personne.class.php');
include_once('Mariage.class.php');
error_reporting(E_ALL);
?>
<html>
<head>
<title>Arbre</title>
<link rel="stylesheet" media="screen" href="style.css">

</head>
<body>
<?php
date_default_timezone_set('Europe/Paris');
	/*static $pos=array();
	$pere=new Personne('pere','02/03/1940, Bordeaux','04/07/2004','','Michel','Durand',null,null);
	$pere->sexe='M';
	$mere=new Personne('mere','02/09/1942, Talence',false,'','Eleonore','Martin',null,null);
	$mere->sexe='F';
	$epoux_mere=new Personne('epoux2_mere','02/03/1945, Paris',false,'','Emile','Blanc',null,null);
	$epoux_mere->sexe='M';
	$enfant4_1=new Personne('enfant_epoux2','07/08/1968, Bordeaux',false,'','Julie','Blanc',$epoux_mere,$mere);
	$enfant4_1->sexe='F';
	$mere->mariages=array(new Mariage('mere','epoux2_mere','06/08/1958',null,array()));
	$mere->mariages[0]->conjoint2=$epoux_mere;
	//$mere->mariages[0]->enfants=array($enfant4_1);
	
	$personne_source=new Personne('source','01/01/1970',false,'','Robert','Durand',$pere,$mere);
	$personne_source->pos=new Coord(2,2);
	$personne_source->sexe='M';
	$epouse1=new Personne('femme_source','02/03/1971, Paris',false,'','Mathilde','Rimel',null,null);
	$epouse1->sexe='F';
	$epouse2=new Personne('femme_source2','02/03/1966, Paris',false,'','Marie','Polity',null,null);
	$epouse2->sexe='F';
	//$epouse3=new Personne('femme_enfant1','02/03/1966, Paris',false,'','Marie','Polity',null,null);
	//$epouse3->sexe='F';
	$epoux2=new Personne('homme','02/03/1967, Paris',false,'','Lucien','Verger',null,null);
	$epoux2->sexe='M';
	
	$enfant1_1=new Personne('enfant1','07/08/1988, Bordeaux',false,'','Mathieu','Durand',$personne_source,$epouse1);
	$enfant1_1->sexe='M';
	$epouse_enfant1=new Personne('epouse_enfant1','07/08/1988, Talence',false,'','Adelaïde','Leroy',null,null);
	$epouse_enfant1->sexe='F';
	
	$enfant1_1->mariages=array(new Mariage('enfant1','epouse_enfant1','06/11/2008',null,array()));
	$enfant1_1->mariages[0]->conjoint2=$epouse_enfant1;
	//$enfant1_1->mariages[0]->enfants=array($enfant4_1);
	
	$enfant1_2=new Personne('enfant2','07/04/1989, Bordeaux',false,'','Nicolas','Durand',$personne_source,$epouse1);
	$enfant1_2->sexe='M';
	$epouse_enfant1->mariages=array(new Mariage('epouse_enfant1','enfant2','06/11/2009',null,array()));
	$epouse_enfant1->mariages[0]->conjoint2=$enfant1_2;
	
	$enfant1_3=new Personne('enfant3','07/04/1989, Bordeaux',false,'','Sophie','Durand',$personne_source,$epouse1);
	$enfant1_3->sexe='F';
	$enfant1_4=new Personne('enfant4','07/04/1989, Bordeaux',false,'','Cyrielle','Durand',$personne_source,$epouse1);
	$enfant1_4->sexe='F';
	$enfant2_1=new Personne('enfant5','09/05/1987, Paris',false,'','Martin','Durand',$personne_source,$epouse2);
	$enfant2_1->sexe='M';
	$enfant2_2=new Personne('enfant6','09/05/1987, Paris',false,'','Martine','Durand',$personne_source,$epouse2);
	$enfant2_2->sexe='F';
	$enfant3_1=new Personne('enfant7','01/09/1988, Paris',false,'','Laurent','Verger',$epoux2,$epouse2);
	$enfant3_1->sexe='M';
	$personne_source->mariages=array(new Mariage('source','femme_source','06/06/1988',null,array()),
									 new Mariage('source','femme_source2','06/06/1986',null,array()));
									 
	
	$epouse2->mariages=array(new Mariage('femme_source2','homme','06/08/1989',null,array()));
	$epouse2->mariages[0]->conjoint2=$epoux2;
	$epouse2->mariages[0]->enfants=array($enfant3_1);
									 
	$personne_source->mariages[0]->conjoint2=$epouse1;
	$personne_source->mariages[0]->enfants=array($enfant1_1,$enfant1_2,$enfant1_3,$enfant1_4);
	$personne_source->mariages[1]->conjoint2=$epouse2;
	$personne_source->mariages[1]->enfants=array($enfant2_1,$enfant2_2);
	
	$pere->mariages=array(new Mariage('pere','mere','06/02/1960',null,array()));
	$pere->mariages[0]->conjoint2=$mere;
	$pere->mariages[0]->enfants=array($personne_source);
	$pere=serialize($pere);
	// $inF = fopen('serialized.txt',"w");
	// fwrite($inF,$pere);
	// fclose($inF);
	*/
	Personne::$id_session=$_GET['id_session'];
	$requete_efface_positions='DELETE FROM positions WHERE id_session='.Personne::$id_session;
	Requete::query($requete_efface_positions);
	$personne_source=new Personne('http://gw2.geneanet.org/index.php3?b=jboidier&lang=fr;p=jean;n=veurier;oc=2');
	$personne_source->pos=new Coord(0,0);
	$personne_source->dessinerArbre(0);
	Personne::corriger_placement_epoux();
	Personne::supprimer_traits_sans_issue();
	Personne::corriger_traits();
	Personne::corriger_lignes_horizontales();
	Personne::corriger_cadrage(500,0);
	Personne::afficher_traits();
	Personne::afficher_boites();
	//echo '<pre>';print_r(Personne::$liste_boites);echo '</pre>';
	
	//$inF = fopen('html_content.html',"w"); 
	//fwrite($inF,ob_get_contents());
	//ob_end_clean ();

?>
</body>
</html>