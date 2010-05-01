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
	
	function fixerNiveauCourant() {
		Personne::$niveau_courant=intval($this->pos->y / (HAUTEUR_PERSONNE+HAUTEUR_GENERATION));
		//echo 'Boite trouvee en y='.$this->pos->y.', niveau courant fixe a '.Personne::$niveau_courant."\n";
	}

        function deplacerExistanteDe(Coord $coord) {
            $this->pos->incr($coord->x,$coord->y);
            $traits_concernes=Trait::getTraitsConcernesPar($this->id);
            foreach($traits_concernes as $trait) {
                switch ($trait->type) {
                    case 'conjoints' :
                        $mariage=ComplexObjectToGet('Mariage', array('conjoint1'=>$trait->id,'conjoint2'=>$trait->id2));
                        $enfants_mariage=ComplexObjectToGet('EnfantMariage', array('id_mariage'=>$mariage->id), 'all');
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

        static function getDeplacementAFaire($coords, $ids_a_exclure) {
            $liste_x=array();
            $liste_y=array();
            foreach($coords as $coord) {
                $liste_x[]=$coord->x;
                $liste_y[]=$coord->y;
            }
            $min=new Coord(array('x'=>min($liste_x), 'y'=>min($liste_y)));
            $max=new Coord(array('x'=>max($liste_x), 'y'=>max($liste_y)));
            $intervalle_a_verifier=new Intervalle(array(
                'pos1'=>new Coord(array('x'=>$min->x - LARGEUR_PERSONNE,
                                        'y'=>$min->y - HAUTEUR_PERSONNE)),
                'pos2'=>new Coord(array('x'=>$max->x + LARGEUR_PERSONNE,
                                        'y'=>$max->y + HAUTEUR_PERSONNE))));
            $x_debut=$intervalle_a_verifier->pos1->x;
            $espacement_pos=$intervalle_a_verifier->pos2->x - $intervalle_a_verifier->pos1->x;
            $boite_existante=true;
            while (!is_null($boite_existante)) {
                if (is_object($boite_existante)) {
                    $intervalle_a_verifier->pos1->x=$boite_existante->pos->x + ESPACEMENT_INCONNUS;
                    $intervalle_a_verifier->pos2->x=$boite_existante->pos->x + ESPACEMENT_INCONNUS + $espacement_pos;
                }
                $boite_existante=Boite::existe_dans_intervalle($intervalle_a_verifier,$ids_a_exclure);
            }
            return $intervalle_a_verifier->pos1->x - $x_debut;
        }

        static function existe_dans_intervalle(Intervalle $intervalle, $ids_a_exclure) {
            $conditions=array('pos_x>'.$intervalle->pos1->x,
                              'pos_x<'.$intervalle->pos2->x,
                              'pos_y>'.$intervalle->pos1->y,
                              'pos_y<'.$intervalle->pos2->y);
            foreach($ids_a_exclure as $id_a_exclure)
                $conditions=array_merge($conditions,array('id NOT LIKE \''.$id_a_exclure.'\''));
            $boites_existantes=ComplexObjectToGet('Boite',$conditions,'all');
            if (is_null($boites_existantes))
                return null;
            usort($boites_existantes,'trier_pos_boites');
            return $boites_existantes[0];
        }
}

function trier_pos_boites($boite1, $boite2) {
    if ($boite1->pos->x == $boite2->pos->y)
        return 0;
    return ($boite1->pos->x < $boite2->pos->y)? 1 : -1;
}