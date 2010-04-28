<?php

class Geneanet {
    static $ligne_geneanet_classique='<li style="vertical\-align: middle;list\-style\-type: (circle|disc|square);?">(?:<img[^>]*> )?<a href="([^"]+)">([^<]+)</a>';
    static $ligne_geneanet_classique2='<li style="vertical\-align: middle;list\-style\-type: (?:circle|disc|square);?">(?:<img[^>]*> )?<a href="[^"]+">[^<]+</a>';
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
    static $mois=array('janvier'=>'JAN','février'=>'FEB','mars'=>'MAR','avril'=>'AVR','mai'=>'MAY','juin'=>'JUN',
					   'juillet'=>'JUL', 'août'=>'AUG', 'septembre'=>'SEP', 'octobre'=>'OCT', 'novembre'=>'NOV', 'décembre'=>'DEC');
	
    static $nom_domaine;

    function Geneanet($page,&$o) {
        preg_match(Geneanet::$regex_etat_civil, $page, $r_etat_civil);
        $naissance = '';
        $mort = '';
        $autres = '';
        if (isset($r_etat_civil[1])) {
            preg_match_all(Geneanet::$regex_etat_civil_autres, $r_etat_civil[1], $r_etat_civil_infos);
            foreach ($r_etat_civil_infos[1] as $info) {
                $info_naissance = preg_match(Geneanet::$regex_etat_civil_naissance, $info, $r_naissance);
                if ($info_naissance!=0) {
                    $naissance = $r_naissance[1];
                    list($o->date_naissance, $o->lieu_naissance) = Geneanet::decomposer_naissance_mort($naissance);
                }
                $info_deces = preg_match(Geneanet::$regex_etat_civil_deces, $info, $r_deces);
                if ($info_deces!=0) {
                    $mort = $r_deces[1];
                    list($o->date_mort, $o->lieu_mort) = Geneanet::decomposer_naissance_mort($mort);
                }
                if ($info_naissance==0 && $info_deces==0) {
                    if (!empty($autres))
                        $info.='. ';
                    $autres.=$info;
                }
            }
        }
        $o->naissance = $naissance;
        $o->mort = $mort;
        $o->autres = $autres;

        $possede_parents=preg_match(Geneanet::$regex_parents, $page, $r_parents);
        //$o->url_parents=array('pere'=>Geneanet::$nom_domaine.$r_parents[2],'mere'=>Geneanet::$nom_domaine.$r_parents[5]);
        if ($possede_parents) {
            $o->pere=Geneanet::url_to_id(Geneanet::$nom_domaine.$r_parents[2]);
            $o->mere=Geneanet::url_to_id(Geneanet::$nom_domaine.$r_parents[5]);
        }
        preg_match(Geneanet::$regex_patronyme, $page, $r_patronyme);
        if (!isset($r_patronyme[0])) {
            echo 'Prenom/Nom pas trouv&eacute; pour ' . $o->to_id() . '<br />';
        } else {
            $sexe = $r_patronyme[1];
            $prenom = $nom = '';
            if (empty($r_patronyme[2])) {
                $prenom_nom = $r_patronyme[4];
                $prenom_nom_exploded = explode(' ', $prenom_nom);
                foreach ($prenom_nom_exploded as $mot) {
                    $regex_classification = '#[0-9]*°?#is';
                    $comporte_classification = preg_match($regex_classification, $mot, $resultat_classification)!=0;
                    if ($mot==mb_strtoupper($mot, 'UTF-8') && ! (empty($nom) && $comporte_classification)) {
                        if (!empty($nom))
                            $nom.=' ';
                        $nom.=$mot;
                    }
                    else {
                        if (!empty($prenom))
                            $prenom.=' ';
                        $prenom.=$mot;
                    }
                }
                $nom = mb_strtolower($nom, 'UTF-8');
            }
            else {
                $prenom = $r_patronyme[2];
                $nom = mb_strtolower($r_patronyme[3], 'UTF-8');
                $nom_exploded = explode(' ', $nom);
                for ($i = 0; $i < count($nom_exploded); $i++)
                    if (!empty($nom_exploded[$i]))
                        $nom_exploded[$i][0] = mb_strtoupper($nom_exploded[$i][0], 'UTF-8');
                $nom = implode(' ', $nom_exploded);
            }
            $o->prenom = $prenom;
            $o->nom = $nom;
        }

        if (!isset($sexe))
            $o->sexe = 'I';
        else
            $o->sexe = $sexe;
                    
        preg_match(Geneanet::$regex_mariages,$page,$r_mariages);
        $o->mariages=array();
        if (isset($r_mariages[1])) {
            preg_match_all(Geneanet::$regex_mariages_conjoints,$r_mariages[1],$r_mariages_conjoints);

            for($i=0;$i<count($r_mariages_conjoints[0]);$i++) {
                $url_conjoint=Geneanet::$nom_domaine.$r_mariages_conjoints[3][$i];
                if (!empty($r_mariages_conjoints[2][$i])) { // Détails mariage
                    preg_match(Geneanet::$regex_mariages_conjoints_details,$r_mariages_conjoints[2][$i],$r_detail_mariage);
                    list($date_mariage,$lieu_mariage)=Geneanet::decomposer_naissance_mort($r_detail_mariage[1].$r_detail_mariage[2]);
                }
                else
                    $date_mariage=$lieu_mariage='';
                $enfants=array();
                if (!empty($r_mariages_conjoints[5][$i])) { // Enfants
                    preg_match_all(Geneanet::$regex_mariages_enfants,$r_mariages_conjoints[5][$i],$r_enfants);
                    for($j=0;$j<count($r_enfants[0]);$j++) {
                        $url_enfant=Geneanet::$nom_domaine.$r_enfants[2][$j];
                        $enfants[]=Geneanet::url_to_id($url_enfant);
                    }
                }
                $id_conjoint=Geneanet::url_to_id($url_conjoint);

                list($id_homme,$id_femme)=Personne::toHomme_Femme($o,$id_conjoint);
                $mariage=new Mariage(array('id'=>'','conjoint1'=>$id_homme,'conjoint2'=>$id_femme,'date_mariage'=>$date_mariage,'lieu_mariage'=>$lieu_mariage));
                $mariage->enfants=$enfants;
                $o->mariages[$i]=$mariage;
            }
        }
    }

    function decomposer_naissance_mort($str) {
        $regex_date = '#le&nbsp;([^&]+)&nbsp;([^&]+)&nbsp;([0-9]+)(?: julien)?#isu';
        $format_normal = preg_match($regex_date, $str, $r_date)!=0;
        if ($format_normal) {
            $jour = strlen($r_date[1])==1 ? '0' . $r_date[1] : $r_date[1];
            $jour = str_replace('er', '', $jour);
            $mois = Geneanet::$mois[utf8_decode($r_date[2])];
            $annee = $r_date[3];
            $date = $jour . ' ' . $mois . ' ' . $annee;
        } else {
            $regex_date_courte = '#(en|apr.s|avant|environ|vers|entre)&nbsp;([0-9]+)(?: julien)?(&nbsp;et&nbsp;([0-9]+)(?: julien)?)?#isu';
            $format_court = preg_match($regex_date_courte, $str, $r_date)!=0;
            if ($format_court) {
                $date = $r_date[2];
                switch ($r_date[1]) {
                    case 'avant': $date = 'BEF ' . $date;break;
                    case 'environ':
                    case 'vers': $date = 'ABT ' . $date;break;
                    case 'en':break;
                    default:$date = 'AFT ' . $date;break;
                }
            } else {
                $regex_date_courte_mois = '#(en|apr.s|avant|vers|environ|entre)&nbsp;([^&]+)&nbsp;([0-9]+)(?: julien)?(&nbsp;et&nbsp;([^&]+)&nbsp;([0-9]+)(?: julien)?)?#isu';
                $format_court_mois = preg_match($regex_date_courte_mois, $str, $r_date_mois)!=0;
                if ($format_court_mois) {
                    $mois = Geneanet::$mois[utf8_decode($r_date[2])];
                    $annee = $r_date[3];
                    $date = $mois . ' ' . $annee;
                    switch ($r_date[1]) {
                        case 'avant': $date = 'BEF ' . $date;break;
                        case 'environ':
                        case 'vers': $date = 'ABT ' . $date;break;
                        case 'en':break;
                        default:$date = 'AFT ' . $date;break;
                    }
                }
            }
        }
        if (!$format_normal && ! $format_court) {
            if ($str==' ' || empty($str))
                $date = -9999;
            else {
                //echo 'Format de date inconnu pour '.$str.'<br />';
                $date = '';
            }
            $r_date[0] = $str;
        }
        $regex_age_mort = '# , . l\'.ge de [0-9]* (?:ans?|mois|jours?)#isu';
        $regex_nettoyage_lieu = '#^ ?[-,]? ?#isu';
        if (!isset($r_date[0]))
            return array('', '');

        $lieu = substr($str, strlen($r_date[0]) + 1, strlen($str) - 1 - strlen($r_date[0]));
        $age_mort_trouve = preg_match($regex_age_mort, $lieu, $r_lieu)!=0;
        $nettoyage_necessaire = preg_match($regex_nettoyage_lieu, $lieu, $r_lieu2)!=0;
        if ($age_mort_trouve)
            $lieu = substr($lieu, 0, strlen($lieu) - strlen($r_lieu[0]));
        if ($nettoyage_necessaire)
            $lieu = substr($lieu, strlen($r_lieu2[0]), strlen($lieu) - strlen($r_lieu2[0]));
        if (!$lieu)
            $lieu = '';
        return array($date, $lieu);
    }
    
    function to_id() {
        return Geneanet::url_to_id($this->url);
    }

    static function url_to_id($url) {
        $regex='#&lang=fr;*(?:pz=[^;]+;)?(?:nz=[^;]+;)?(?:ocz=[^;]+;)?(.*)#is';
        preg_match($regex,$url,$resultat);
        if (count($resultat)==2)
            return $resultat[1];
        else
            return $url;
    }
}
Geneanet::$regex_etat_civil='#<td class="highlight2">&nbsp; .tat civil</td>[^<]*</tr></table>[^<]*<ul>[^<]*'
                           .'((?:<li>[^<]*</li>[^<]*)+)</ul>#isu';
Geneanet::$regex_parents='#<td class="highlight2">&nbsp; Parents</td>[^<]*</tr></table>[^<]*<ul>[^<]*'
                        .Geneanet::$ligne_geneanet_classique.'(?:(?:(?!</li>).)*)</li>[^<]*'
                        .Geneanet::$ligne_geneanet_classique.'#isu';
Geneanet::$regex_mariages='#<td class="highlight2">&nbsp; Mariage(?:\()?s?(?:\))? (?:et enfant(?:\()?s?(?:\))?)?(?:<span[^>]+>[^>]*>)*</td>[^<]*</tr></table>(?:[^<]*</h3>)?[^<]*(<ul>[^<]*'
                         .'(?:<li style="vertical\-align: middle;list\-style\-type: (?:circle|disc|square)">Mari.e? ?(?:<em>[^<]+</em>)?[^a]*avec <a href="(?:[^"]+)">(?:[^<]+)</a>(?: <em><bdo dir="ltr">[^<]*</bdo></em>)?'
                         .'(?:(?:(?!, dont).)*, dont[^<]*<ul>[^<]*(?:'.Geneanet::$ligne_geneanet_classique2.'(?:(?:(?!</li>).)*)</li>[^<]*)+</ul>)*[^<]*</li>[^<]*)+</ul>)#isuU';
Geneanet::$regex_mariages_conjoints='#<li style="vertical\-align: middle;list\-style\-type: (circle|disc|square)">Mari.e? ?((?:<em>[^<]+</em>)?)[^a]*avec <a href="([^"]+)">([^<]+)</a>(?: <em><bdo dir="ltr">[^<]*</bdo></em>)?'
                                   .'((?:(?:(?!, dont).)*, dont[^<]*<ul>[^<]*(?:'.Geneanet::$ligne_geneanet_classique2.'(?:(?:(?!</li>).)*)</li>[^<]*)+</ul>)?)[^<]*</li>#isu';
Geneanet::$regex_mariages_enfants='#'.Geneanet::$ligne_geneanet_classique.'#isu';
Geneanet::$regex_patronyme='#<img src="http://images.geneanet\.org/v3/pictos_geneweb/[^/]+/(?:(?:saisie-(?:homme|femme))|sexeinconnu)\.gif" alt="(H|F|\?)" title="(?:H|F|\?)" />'
                          .'</td>[^<]*<td class="highlight2">&nbsp;(?:(?:[^<]*<a href="[^"]*">([^<]+)</a>[^<]*<a href="[^"]*">([^<]+)</a>)|..([^<]*)</td>)#isu';

?>
