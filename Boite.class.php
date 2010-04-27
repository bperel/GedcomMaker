<?php 
include_once('ComplexObject.class.php');
include_once('Coord.class.php');
include_once('Dimension.class.php');

class Boite extends ComplexObject {
	static $prefixes_objets=array('pos'=>'Coord','dimension'=>'Dimension');
	static $identifiants=array('id');
	var $id;
	var $sexe;
	var $recursion;
	var $contenu;
	var $pos;
	var $dimension;
	
	function fixNiveauCourant() {
		Personne::$niveau_courant=intval($this->pos->y / (HAUTEUR_PERSONNE+HAUTEUR_GENERATION));
		//echo 'Boite trouvee en y='.$this->pos->y.', niveau courant fixe a '.Personne::$niveau_courant."\n";
	}

        function deplacerExistanteDe(Coord $coord) {
            $this->pos->incr($coord->x,$coord->y);
            $traits_concernes=Trait::getTraitsConcernesPar($this->id);
            $enfants_mariage=ComplexObjectToGet('EnfantMariage', array('id_mariage'=>$mariage->id), 'all');
            foreach($traits_concernes as $trait) {
                switch ($trait->type) {
                    case 'conjoints' :
                        $mariage=ComplexObjectToGet('Mariage', array('conjoint1'=>$trait->id,'conjoint2'=>$trait->id2));
                        switch($trait->name) {
                            case 'conjoint': case 'liaison_trait_enfants': // Trait de liaison /  Trait entre la liaison et le trait des enfants
                                $trait->pos_debut->incr($coord->x, $coord->y);
                                $trait->update();
                            case 'liaison_trait_enfants': // Trait entre la liaison et le trait des enfants
                                $trait_enfants=ComplexObjectToGet('Trait', array('id'=>$trait->id,'id2'=>$trait->id2,'name'=>'trait_enfants'));
                                if ($trait->pos_debut->x < $trait_enfants->pos_debut->x) {// Le trait vertical a une abscisse inférieure au trait des enfants
                                    $trait_enfants->pos_debut->decr($coord->x, $coord->y);
                                    /*foreach ($enfants_mariage as $enfant_mariage) {
                                        $boite_enfant=ComplexObjectToGet('Boite', array('id'=>$enfant_mariage->id_enfant));
                                        $boite_enfant->deplacerExistanteDe(new Coord(array('x'=>-1*(LARGEUR_PERSONNE + LARGEUR_BORDURE * 4),'y'=>0)));
                                    }*/
                                }
                                if ($trait->pos_debut->x > $trait_enfants->pos_debut->x + $trait_enfants->width) { // Le trait vertical a une abscisse supérieure à la fin du trait des enfants
                                    $trait_enfants->pos_debut->decr($coord->x,$coord->y);
                                    /*foreach ($enfants_mariage as $enfant_mariage) {
                                        $boite_enfant=ComplexObjectToGet('Boite', array('id'=>$enfant_mariage->id_enfant));
                                        $boite_enfant->deplacerExistanteDe(new Coord(array('x'=>LARGEUR_PERSONNE + LARGEUR_BORDURE * 4,'y'=>0)));
                                    }*/
                                }
                                $trait_enfants->update();
                            break;

                        }
                    break;
                }
            }
        }

        function deplacerExistanteVers(Coord $coord) {
            $this->deplacerExistanteDe (new Coord(array('x'=>$coord->x - $this->pos->x, 'y'=>$coord->y - $this->pos->y)));
        }
}