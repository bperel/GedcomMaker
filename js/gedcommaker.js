var id_g;
var id_caller_g;
var id_tmp_g;
var id2;
var next2;
var analyzed_ids=new Array();
var nb_descendants=new Array();
var pile=new Array();
var traiter;
var ajax_is_loading=false;
var conjoints=new Array();

function routine() {
	if (pile_personnes.length > 0 || ajax_is_loading) {
		if (!ajax_is_loading)
			loadPersonne(pile_personnes.pop());
	}
	setTimeout(routine, 500);
}

function loadPersonne (id) {
	ajax_is_loading=true;
	id_g=id;
	new Ajax.Request('Personne.class.php', {
		parameters:script+'=true&id_session='+id_session_g+'&serveur='+serveur_g+'&pseudo='+pseudo_g+'&autres_args='+id.replace('%',';pcnt;'), 
		asynchronous: true,
		onSuccess: function(transport) {
			var resultat=transport.headerJSON;
		    
			if (script=='analyse') {
				if (!resultat || resultat.length==0) {
				    alert('L\'analyse de '+id_g+' a retourn� : \n\n'+transport.responseText);
				    if (nb_barres_ajoutees==0)
				    	definir_termine(id_g);
			    	ajax_is_loading=false;
			    	pile_personnes=new Array();
				    return;
			    }
			    var niveau_suivant=parseInt($(id_g).readAttribute('name').substring('niveau'.length))+1;
			    var nb_barres_ajoutees=0;
			    if (typeof resultat.pere != 'undefined') {
			    	var id_pere=resultat.pere['id'];
			    	nb_barres_ajoutees+=ajouter_barre(niveau_suivant,id_pere, id_g,'P�re',resultat.pere['action'],false);
			    }
			    if (typeof resultat.mere != 'undefined') {
			    	var id_mere=resultat.mere['id'];
			    	nb_barres_ajoutees+=ajouter_barre(niveau_suivant,id_mere, id_g,'M�re',resultat.mere['action'],false);
			    }
			    if (typeof resultat.mariages != 'undefined') {
				    for(var i=0;i<resultat.mariages.length;i++) {
				    	var id_conjoint=resultat.mariages[i]['conjoint']['id'];
				    	/*if (conjoints.indexOf(id)==-1)
				    		conjoints[id]=new Array();
				    	if (conjoints.indexOf(id_conjoint)==-1)
				    		conjoints[id_conjoint]=new Array();
				    	conjoints[id].push(id_conjoint);
				    	conjoints[id_conjoint].push(id);*/
				    	nb_barres_ajoutees+=ajouter_barre(niveau_suivant,id_conjoint, id_g,'Epoux',resultat.mariages[i]['conjoint']['action'],false);
				    	if (typeof resultat.mariages[i].enfants != 'undefined') {
					    	for (var j=0;j<resultat.mariages[i]['enfants'].length;j++) {
						    	var id_enfant=resultat.mariages[i]['enfants'][j]['id'];
						    	nb_barres_ajoutees+=ajouter_barre(niveau_suivant,id_enfant, id_g,'Enfant',resultat.mariages[i]['enfants'][j]['action'],id_conjoint);
						    }
				    	}
				    }
			    }
				afficher_boite(resultat.boites.creees[0]);
			    if (nb_barres_ajoutees==0)
			    	definir_termine(id_g);
			}
			else if (script=='make_tree') {
				if (!resultat || resultat.length==0) {
				    alert('L\'analyse de '+id_g+' a retourn� : \n\n'+transport.responseText);
			    	ajax_is_loading=false;
			    	pile_personnes=new Array();
				    return;
				}
				afficher_boite(resultat.boites.creees[0]);
			}
		    ajax_is_loading=false;
		    
		}
	});
}

function afficher_boite(boite) {
	var elboite=new Element('div',{'id':boite['id']}).addClassName('personne '+boite['sexe'])
										    	     .setStyle({'left':boite['pos']['x'],'top':boite['pos']['y']+'px',
										    		   		    'width':boite['dimension']['width']+'px','height':boite['dimension']['height']+'px'})
										    	     .update(boite['contenu'])
										    	     .insert(new Element('div').addClassName('recursion'));
	$('body').insert(elboite);
}

function definir_termine(id) {
	$(id+'_percentImage').replace('OK');

	var id_caller=id_to_caller(id);
	if (!$(id_caller))
		return;
	var nb_enfants_caller=pile[id_caller]?pile[id_caller].length:1;
	myJsProgressBarHandler.setPercentage(id_caller,'+'+(100/nb_enfants_caller));
	if (myJsProgressBarHandler.getPercentage(id_caller)>99)
		definir_termine(id_caller);

	return;
}

function id_to_caller(id) {
	var callers=new Array();
	for (var caller in pile)
		for (var i=0;i<pile[caller].length;i++)
			if (pile[caller][i]==id)
				callers.push(caller);
	return callers.length==0?-1:(callers.length==1?callers[0]:callers);
}

function ajouter_barre(niveau, id, id_caller,type, etat, id_conjoint) {
	var texte=id;
	if (id_to_caller(id)!=-1)
		etat='already_done';
	
	if (etat!='todo') {
		var numero=1;
		while ($(id+numero))
			numero++;
		id+='/'+numero;
		texte+=' ['+etat+']';
	}
	if (type=='Enfant')
		niveau++;
	texte=type+' : '+texte;
	var couleur="#000000";
	var num_image='';
	switch(type) {
		case 'Enfant':couleur='#04B404';num_image=1;break;
		case 'P�re':couleur='#0101DF';num_image=2;break;
		case 'M�re':couleur='#FF0000';num_image=3;break;
		case 'Epoux':couleur='#9A2EFE';num_image=4;break;
	}
	var progressBar=new Element('span').setStyle({'color':couleur,'fontWeight':'bold'}).update(texte);
	var espacement='';
	for (var a=0;a<niveau;a++)
		espacement+='&nbsp;&nbsp;';
	$(id_caller).insert(progressBar);
	$(progressBar).insert({'before':new Element('br')});
	$(progressBar).insert({'before':espacement});

	if (etat=='todo') {
		var progressBarTexte=new Element('span',{'id':id, 'name':'niveau'+niveau}).addClassName('progressBar');
		$(progressBar).insert({'after':progressBarTexte}).insert({'after':new Element('br')});
		$(progressBarTexte).insert({'before':espacement});
		new JS_BRAMUS.jsProgressBar($(id), 0);
		JS_BRAMUS.jsProgressBarHandler.prototype.pbArray[id]	= new JS_BRAMUS.jsProgressBar($(id), parseInt($(id).innerHTML.replace("%","")), {animate: false, width: 120, height: 12, barImage: 'images/bramus/percentImage_back'+num_image+'.png'}); 
		if (typeof pile[id_caller] == 'undefined') {
			pile[id_caller]=new Array();
		}
		pile[id_caller].push(id);
		pile_personnes.push(id);
		return 1;
	}
	return 0;
}