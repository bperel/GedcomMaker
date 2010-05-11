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

        static $liste_boites_deplacees;
        static $liste_boites_deplacees_temp;
        static $liste_boites_a_deplacer;
        static $liste_traits_deplaces;
        static $liste_traits_ajoutes;

        function getBoitesPrecedenteSuivante() {
            $boite_precedente=$boite_suivante=null;
            //Récupérer la boite précédente et suivante dans la fratrie
            $id_mariage=ComplexObjectFieldToGet('EnfantMariage', 'id_mariage', array('id_enfant'=>$this->id),true);
            if (is_null($id_mariage)) {
                $personne=ComplexObjectToGet('Personne',array('id'=>$this->id));
                if (is_null($personne))
                    return array(null,null);
                $mariage_parents=ComplexObjectToGet('Mariage', array('conjoint1'=>$personne->pere, 'conjoint2'=>$personne->mere));
            }
            else
                $mariage_parents=ComplexObjectToGet('Mariage',array('id'=>$id_mariage));
            if (!is_null($mariage_parents)) {
                foreach($mariage_parents->enfants as $i=>$id_enfant) {
                    if ($id_enfant === $this->id) {
                        if ($i>0)
                            $boite_precedente=ComplexObjectToGet('Boite',array('id'=>$mariage_parents->enfants[$i-1]));
                        if ($i<count($mariage_parents->enfants)-1)
                            $boite_suivante=ComplexObjectToGet('Boite',array('id'=>$mariage_parents->enfants[$i+1]));
                    }
                }
            }
            return array($boite_precedente, $boite_suivante);
        }
        
	function fixerNiveauCourant() {
		Personne::$niveau_courant=intval($this->pos->y / (HAUTEUR_PERSONNE+HAUTEUR_GENERATION));
		//echo 'Boite trouvee en y='.$this->pos->y.', niveau courant fixe a '.Personne::$niveau_courant."\n";
	}

        function deplacerExistanteDe(Coord $coord) {
            foreach (Boite::$liste_boites_deplacees as $boite_deplacee)
                if ($this->equals($boite_deplacee))
                    return;
            $this->pos->incr($coord->x,$coord->y);
            $this->mettre_dans_retour();
            $this->update();

            Boite::$liste_boites_deplacees[]=$this;
            Boite::$liste_boites_deplacees_temp[]=$this;
            $traits_concernes=Trait::getTraitsConcernesPar($this->id);
            foreach($traits_concernes as $trait) {
                switch ($trait->type) {
                    case 'conjoints' :
                        $mariage=ComplexObjectToGet('Mariage', array('conjoint1'=>$trait->id,'conjoint2'=>$trait->id2));
                        switch($trait->name) {
                            case 'liaison': // Trait de liaison /  Trait entre la liaison et le trait des enfants
                                $id_conjoint=$this->id === $mariage->conjoint1 ? $mariage->conjoint2 : $mariage->conjoint1;
                                $trait_inverse=clone $trait;
                                $trait_inverse->id=$trait->id2;$trait_inverse->id2;
                                $traits_deplaces=Boite::$liste_traits_deplaces;
                                foreach (Boite::$liste_traits_deplaces as $trait_deplace)
                                    if ($trait_deplace->equals($trait))
                                        break 2;
                                $trait->pos_debut->incr($coord->x, $coord->y);
                                Boite::$liste_traits_deplaces[]=$trait;
                                $trait->mettre_dans_retour();
                                $trait->update();
                                $boite_conjoint=ComplexObjectToGet('Boite', array('id'=>$id_conjoint));
                                $boite_conjoint->deplacerExistanteDe($coord);
                            break;
                            case 'liaison_trait_enfants': // Trait entre la liaison et le trait des enfants
                                $id_conjoint=$this->id === $mariage->conjoint1 ? $mariage->conjoint2 : $mariage->conjoint1;
                                $trait_inverse=clone $trait;
                                $trait_inverse->id=$trait->id2;$trait_inverse->id2;
                                $traits_deplaces=Boite::$liste_traits_deplaces;
                                foreach (Boite::$liste_traits_deplaces as $trait_deplace)
                                    if ($trait_deplace->equals($trait))
                                        break 2;
                                $trait->pos_debut->incr($coord->x, $coord->y);
                                Boite::$liste_traits_deplaces[]=$trait;
                                $trait->mettre_dans_retour();
                                $trait->update();
                                $boite_conjoint=ComplexObjectToGet('Boite', array('id'=>$id_conjoint));
                                $boite_conjoint->deplacerExistanteDe($coord);
                                
                                foreach($mariage->enfants as $id_enfant) {
                                    ComplexObjectToGet('Boite',array('id'=>$id_enfant))
                                        ->deplacerExistanteDe($coord);
                                }
                                $trait_enfants=ComplexObjectToGet('Trait', array('id'=>$trait->id,'id2'=>$trait->id2,'name'=>'trait_enfants'));
                                if (is_null($trait_enfants))
                                    $trait_enfants=ComplexObjectToGet('Trait', array('id'=>$trait->id2,'id2'=>$trait->id,'name'=>'trait_enfants'));

                                list($boite_premier_enfant, $boite_dernier_enfant)=$mariage->getPremierDernierEnfant();

                                $trait_enfants->pos_debut->x=$boite_premier_enfant->pos->x + LARGEUR_PERSONNE/2;
                                $trait_enfants->width=$boite_dernier_enfant->pos->x - $boite_premier_enfant->pos->x;
                                $trait_enfants->update();

                                Boite::$liste_traits_deplaces[]=$trait_enfants;
                                $trait_enfants->mettre_dans_retour();
                                $trait_enfants->update();
                            break;
                        }
                    break;
                    case 'ligne_enfants__enfant':
                        if ($this->id == $trait->id3) {
                            $mariage_parents=ComplexObjectToGet('Mariage', array('conjoint1'=>$trait->id,'conjoint2'=>$trait->id2));

                            $trait->pos_debut->incr($coord->x, $coord->y);
                            Boite::$liste_traits_deplaces[]=$trait;
                            $trait->mettre_dans_retour();
                            $trait->update();
                            $traits_deplaces=Boite::$liste_traits_deplaces;
                            $trait_enfants_parents=ComplexObjectToGet('Trait', array('id'=>$trait->id,'id2'=>$trait->id2, 'name'=>'trait_enfants'));
                            if (is_null($trait_enfants_parents))
                                $trait_enfants_parents=ComplexObjectToGet('Trait', array('id'=>$trait->id2,'id2'=>$trait->id, 'name'=>'trait_enfants'));

                            if (is_null($trait_enfants_parents)) { // La personne à déplacer était un enfant unique => créer le trait des enfants
                                $personne_deplacee=ComplexObjectToGet('Personne', array('id'=>$this->id));
                                $id_mariage=ComplexObjectFieldToGet('EnfantMariage', 'id_mariage',array('id_enfant'=>$this->id),true);
                                if (!is_null($id_mariage)) {
                                    $mariage_parents_boite_deplacee=ComplexObjectToGet('Mariage', array('id'=>$id_mariage));

                                    $liaison=ComplexObjectToGet('Liaison', array('id'=>$mariage_parents_boite_deplacee->conjoint1,'id2'=>$mariage_parents_boite_deplacee->conjoint2));
                                    if ($this->pos->x + LARGEUR_PERSONNE/2> $liaison->pos->x) {
                                        $pos_debut=new Coord(array('x'=>$liaison->pos->x,'y'=>$this->pos->y-HAUTEUR_GENERATION/2));
                                    }
                                    else {
                                        $pos_debut=new Coord(array('x'=>$this->pos->x,'y'=>$this->pos->y-HAUTEUR_GENERATION/2));
                                    }
                                    $largeur=abs($pos_debut->x - $liaison->pos->x);
                                    $trait_enfants_parents=new Trait(array('id'=>$mariage_parents_boite_deplacee->conjoint1,'id2'=>$mariage_parents_boite_deplacee->conjoint2,
                                                                           'liaison'=>$liaison,
                                                                           'border'=>array('top'=>1),
                                                                           'pos_debut'=>$pos_debut,
                                                                           'width'=>$largeur,
                                                                           'name'=>'trait_enfants',
                                                                           'type'=>'conjoints'));
                                    $trait_enfants_parents->add();
                                    Boite::$liste_traits_ajoutes[]=$trait_enfants_parents;
                                    $trait_enfants_parents->mettre_dans_retour();
                                }
                            }
                            foreach (Boite::$liste_traits_deplaces as $i=>$trait_deplace) {
                                if ($trait_deplace->equals($trait_enfants_parents)) {
                                    $index=$i;
                                    $trait_enfants_parents=& Boite::$liste_traits_deplaces[$index];
                                    break;
                                }
                            }
                            /*foreach($mariage_parents->enfants as $id_enfant) {
                                $boite_enfant=ComplexObjectToGet('Boite',array('id'=>$id_enfant));
                                if (!is_null($boite_enfant))
                                    $boite_enfant->deplacerExistanteDe($coord);
                            }*/
                            list($boite_premier_enfant, $boite_dernier_enfant)=$mariage_parents->getPremierDernierEnfant();
                            if (!is_null($boite_premier_enfant) && !is_null($boite_dernier_enfant)) {
                                $trait_enfants_parents->pos_debut->x=$boite_premier_enfant->pos->x + LARGEUR_PERSONNE/2;
                                $trait_enfants_parents->width=$boite_dernier_enfant->pos->x - $boite_premier_enfant->pos->x - LARGEUR_BORDURE*4;

                                if (!isset($index)) {
                                    Boite::$liste_traits_deplaces[]=$trait_enfants_parents;
                                    $trait_enfants_parents->mettre_dans_retour();
                                }

                                $trait_enfants_parents->update();
                            }
                        }
                     break;
                }
            }
        }

        function deplacerExistanteVers(Coord $coord) {
            $this->deplacerExistanteDe (new Coord(array('x'=>$coord->x - $this->pos->x, 'y'=>$coord->y - $this->pos->y)));
        }

        static function deplacerBoitesInit($boites) {
            Boite::$liste_boites_deplacees=array();
            Boite::$liste_boites_a_deplacer=array();
            Boite::$liste_traits_deplaces=array();
            Boite::$liste_traits_ajoutes=array();
            Boite::deplacerBoites($boites);
        }

        static function deplacerBoites($boites) {
            $boites_precedentes=$boites_suivantes=array();
            $boite_precedente=$boite_suivante=null;
            foreach($boites as &$boite) {
                list($boite_precedente,$boite_suivante)=$boite->getBoitesPrecedenteSuivante();
                if (!is_null($boite_precedente))
                    $boites_precedentes[]=$boite_precedente;
                if (!is_null($boite_suivante))
                    $boites_suivantes[]=$boite_suivante;
            }
            trier($boites_precedentes,'pos->x');
            trier($boites_suivantes,'pos->x');
            if (count($boites_precedentes)!=0)
                $boite_precedente=$boites_precedentes[count($boites_precedentes)-1]; // Boite précédente la plus à droite
            if (count($boites_suivantes)!=0)
                $boite_suivante=$boites_suivantes[0]; // Boite suivante la plus à gauche

            trier($boites, 'pos->x');
            $min=new Coord(array('x'=>$boites[0]->pos->x - ESPACEMENT_INCONNUS, 'y'=>$boites[0]->pos->y - HAUTEUR_PERSONNE));
            $espacement_max_boite=$boites[count($boites)-1]->pos->x + LARGEUR_PERSONNE - $min->x;
            if (!is_null($boite_precedente)) {
                $espacement=ComplexObjectToGet('Personne',array('id'=>$boite_precedente->id))->getEspacementAvec($boites[0]->id);
                $difference_premiere_precedente= $espacement ;
                if ($difference_premiere_precedente > 0) {
                    foreach($boites as &$boite) {
                        $boite->deplacerExistanteDe(new Coord(array('x'=>$difference_premiere_precedente, 'y'=>0)));
                    }
                    $min=new Coord(array('x'=>$boites[0]->pos->x - ESPACEMENT_INCONNUS, 'y'=>$boites[0]->pos->y - HAUTEUR_PERSONNE));
                }
            }
            $max=new Coord(array('x'=>$min->x + $espacement_max_boite,
                                 'y'=>$boites[0]->pos->y + 2*HAUTEUR_PERSONNE));
            $largeur_a_verifier=$max->x - $min->x;
            $x_debut_verif=$min->x;
            $y=($min->y + $max->y)/2;
            
            $boite_existante=true;
            while (!is_null($boite_existante)) {
                if (is_object($boite_existante)) {
                    $x_debut_verif=$boite_existante->pos->x + LARGEUR_PERSONNE + ESPACEMENT_INCONNUS;
                }
                $boite_existante=Boite::existe_dans_intervalle($x_debut_verif, $y, $largeur_a_verifier, $boites, $boite_precedente, $boite_suivante);
            }
        }

        static function existe_dans_intervalle($x_debut_verif, $y, $largeur_a_verifier, &$boites_a_placer, $boite_precedente, $boite_suivante) {
            $conditions=array();
            foreach($boites_a_placer as $boite_a_placer)
                $conditions[]='id NOT LIKE \''.$boite_a_placer->id.'\'';
            $conditions=array_merge($conditions,array(
                              'pos_x>='. $x_debut_verif,
                              'pos_x<='.($x_debut_verif + $largeur_a_verifier),
                              'pos_y>='.($y - HAUTEUR_PERSONNE/2),
                              'pos_y<='.($y + HAUTEUR_PERSONNE/2)));

            $boites_existantes=ComplexObjectToGet('Boite',$conditions,'all');
            if (is_null($boites_existantes))
                return null;

            trier($boites_existantes, 'pos->x');
            trier($boites_a_placer, 'pos->x', 'desc');
            $derniere_boite_a_placer=$boites_a_placer[0];
            $deplacement_a_faire=($derniere_boite_a_placer->pos->x + LARGEUR_PERSONNE + ESPACEMENT_INCONNUS)
                                  - $boites_existantes[0]->pos->x;
            
            if ($deplacement_a_faire > 0) {

                Boite::$liste_boites_deplacees_temp=array();
                foreach($boites_existantes as $boite_existante) {
                    $boite_existante->deplacerExistanteDe(new Coord(array('x'=>$deplacement_a_faire, 'y'=>0)));
                }
                Boite::deplacerBoites(Boite::$liste_boites_deplacees_temp);
                return $boites_existantes[count($boites_existantes)-1];
            }
            return null;
        }
}