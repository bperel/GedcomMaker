<?php 
$id_session=time();
$_POST['id_session']=$id_session;
include_once('Personne.class.php');
include_once('Mariage.class.php');
include_once('Level.class.php');

Personne::$id_session=$id_session;
Personne::initMake_tree();

$args=isset($_GET['args']) ? $_GET['args'] : (isset ($_POST['args']) ? $_POST['args'] : null);
/*if (isset($args)) {
	$serveur=$args['serveur'];
	$pseudo=$args['pseudo'];
	$autres_args=$args['args']; // Autre à faire : http://gw4.geneanet.org/index.php3?b=micheldunoyer&lang=fr;p=joseph;n=ritz
	
}
else {*/
	$serveur='gw0';
	$pseudo='astrofifi';
	$id='p=louis+raymond+henry;n=chevallereau;oc=0';
//}
Personne::$nom_domaine='http://'.$serveur.'.geneanet.org/';
$url='http://'.$serveur.'.geneanet.org/index.php3?b='.$pseudo.'&lang=fr;'.$id;

$niveau=new Level();
$niveau->niveau_courant=0;
$niveau->add();
global $personne_source;
$personne_source=new Personne($url);
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
		var id_session_g=<?=$id_session?>;
		var serveur_g='<?=$serveur?>';
		var pseudo_g='<?=$pseudo?>';
		var id_source='<?=$personne_source->id?>';
		var script='analyse'; 
		pile_personnes.push(id_source);
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
<?php
exit(0);

/*
if (file_exists('serialized.txt')) {
	$inF = fopen('serialized.txt',"r"); 
	$buffer='';
	while (!feof($inF)) {
	   $buffer.= fgets($inF, 4096)."<br>";
	}
	$personne_source=unserialize($buffer);
}*/

$personne_source->analyser();
$personne_source->afficher();
$personne_source->serialiser($serveur,$pseudo,$autres_args);

$numero_famille=Personne::$cpt_personnes+1;

$texte=$personne_source->ecrire_gedcom();

/* Familles */
$personnes_non_referencees=array();
foreach(Personne::$liste_familles as $i=>$famille) {
	$non_referencees=array();
	if ($famille->conjoint1->sexe=='M') {
		$texte.='0 @F'.$i.'@ FAM'."\n";
		$texte.= '1 HUSB @I'.$famille->conjoint1->get_numero_personne().'@'."\n";
		$texte.= '1 WIFE @I'.$famille->conjoint2->get_numero_personne().'@'."\n";
		foreach($famille->enfants as $enfant) {
			$texte.= '1 CHIL @I'.$enfant->get_numero_personne().'@'."\n";
		}
		if (!(empty($famille->date_mariage) && empty($famille->lieu_mariage))) {
			$texte.= '1 MARR'."\n";
			if (!empty($famille->date_mariage))
				$texte.=  '2 DATE '.$famille->date_mariage."\n";
			if (!empty($famille->lieu_mariage))
				$texte.=  '2 PLACE '.$famille->lieu_mariage."\n";
		}
		$non_referencees=$famille->detecter_non_references();
		foreach ($non_referencees as $personne) array_push($personnes_non_referencees, $personne);
	}
}
$personnes_nr_ecrites=array();
foreach ($personnes_non_referencees as $i=>$personne) {
	if (!(array_search($personne['numero'],$personnes_nr_ecrites)===false))
		continue;
	$texte.='0 @I'.$personne['numero'].'@ INDI'."\n";
	if ($personne['numero_famille_origine']!=-1)
		$texte.= '1 FAMC @F'.$personne['numero_famille_origine']."@\n";
	foreach($personne['numeros_familles_souches'] as $id_famille)
		$texte.= '1 FAMS @F'.$id_famille."@\n";
	array_push($personnes_nr_ecrites,$personne['numero']);
}
$texte.='0 TRLR'."\n";
$inF = fopen('arbre.ged',"w");
fwrite($inF,$texte);
fclose($inF);

?>