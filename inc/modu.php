<?php
# *** LICENSE ***
# This file is part of BlogoText.
# http://lehollandaisvolant.net/blogotext/
#
# 2006      Frederic Nassar.
# 2010-2016 Timo Van Neerden.
#
# BlogoText is free software.
# You can redistribute it under the terms of the MIT / X11 Licence.
#
# *** LICENSE ***



function addon_get_conf( $addonName ){
	$infos = addon_get_infos( $addonName );
	if ($infos === false){
		return false;
	}
	$saved = db_addons_params_get( $addonName );

	if (isset($infos['config'])){
		if (!is_array($saved)){
			return $infos['config'];
		} else {
			foreach( $infos['config'] as $key => $vals ){
				$infos['config'][$key]['value'] = (isset($saved[$key])) ? $saved[$key] : $vals['value'] ;
			}
			return $infos['config'];
		}
	}
	return array();
}

function addon_get_infos( $addonName ){
	foreach ($GLOBALS['addons'] as $k){
		if ($k['tag'] == $addonName){
			return $k;
		}
	}
	return false;
}

function addon_edit_params_process( $addonName ){
	$errors = array();

	$addons_status = list_addons();
	$params = addon_get_conf( $addonName );
	$datas = array();

	foreach ($params as $key => $param){
		$datas[$key] = '';
		if ($param['type'] == 'bool'){
			$datas[$key] = (isset($_POST[$key]));
		} else if ($param['type'] == 'int'){
			if (isset($_POST[$key])
			 && is_numeric( $_POST[$key] )
			){
				if (isset($param['value_min'])
				 && $param['value_min'] >= $_POST[$key]
				){
					$errors[$key][] = 'Value is behind limit min.';
				} else if (isset($param['value_max'])
				 && $param['value_max'] <= $_POST[$key]
				){
					$errors[$key][] = 'Value is reach limit max.';
				} else {
					$datas[$key] = htmlentities($_POST[$key],ENT_QUOTES);
				}
			} else {
				// error
				$errors[$key][] = 'No data posted';
			}
		} else if ($param['type'] == 'text'){
			$datas[$key] = htmlentities($_POST[$key],ENT_QUOTES);
		} else {
			// error
			$errors[$key][] = 'not a valid type';
		}
	}

	return db_addons_params_push( $addonName , $datas );
}

function addon_edit_params_form( $addonName ){
	$addons_status = list_addons();
	$infos = addon_get_infos( $addonName );
	$params = addon_get_conf( $addonName );
	$return = '';

	$return .= '<form id="preferences" method="post" action="?addonName='. $addonName .'" >' ;
	$return .= '<div role="group" class="pref">';
	$return .= "\t\t".'<ul id="modules"><li>';

	// on/off checkbox
	$return .= "\t\t".'<span><input type="checkbox" class="checkbox-toggle" name="module_'. $infos['tag'] .'" id="module_'. $infos['tag'] .'" '.(($addons_status[$addonName]) ? 'checked' : '').' onchange="activate_mod(this);" /><label for="module_'. $infos['tag'] .'"></label></span>'."\n";
	// addon name
	$return .= "\t\t".'<span><a href="modules.php?addon_id='.$infos['tag'].'">'. addon_get_translation($infos['name']) .'</a></span>'."\n";
	// version
	$return .= "\t\t".'<span>'.$infos['version'].'</span>'."\n";
	$return .= "\t".'</li>';
	// other infos
	$return .= "\t\t".'<div class=""><code title="'.$GLOBALS['lang']['label_code_theme'].'">'.'{addon_'.$infos['tag'].'}'.'</code>'.addon_get_translation($infos['desc']).'</div>'."\n";
	$return .= "\t".'</ul>'."\n";

	// params form
	$return .= '<div class="form-lines">'."\n";
	foreach ($params as $key => $param){
		$return .= '<p>';
		if ($param['type'] == 'bool'){
			$return .= form_checkbox($key, ($param['value'] === true || $param['value'] == 1), $param['label'][ $GLOBALS['lang']['id'] ]);
		} else if ($param['type'] == 'int'){
			$val_min = (isset($param['value_min'])) ? ' min="'.$param['value_min'].'" ' : '' ;
			$val_max = (isset($param['value_max'])) ? ' max="'.$param['value_max'].'" ' : '' ;
			$return .= "\t".'<label for="'.$key.'">'.$param['label'][ $GLOBALS['lang']['id'] ].'</label>'."\n";
			$return .= "\t".'<input type="number" id="'.$key.'" name="'.$key.'" size="30" '. $val_min . $val_max .' value="'.$param['value'].'" class="text" />'."\n";
		} else if ($param['type'] == 'text'){
			$return .= "\t".'<label for="'.$key.'">'.$param['label'][ $GLOBALS['lang']['id'] ].'</label>'."\n";
			$return .= "\t".'<input type="text" id="'.$key.'" name="'.$key.'" size="30" value="'.$param['value'].'" class="text" />'."\n";
		}
		$return .= '</p>';
	}
	$return .= '</div>';

	// submit box
	$return .= '<div class="submit-bttns">'."\n";
	$return .= hidden_input('_verif_envoi', '1');
	$return .= hidden_input('token', new_token());
	$return .= '<input type="hidden" name="addon_action" value="params" />';
	$return .= '<button class="submit button-cancel" type="button" onclick="annuler(\'preferences.php\');" >'.$GLOBALS['lang']['annuler'].'</button>'."\n";
	$return .= '<button class="submit button-submit" type="submit" name="enregistrer">'.$GLOBALS['lang']['enregistrer'].'</button>'."\n";
	$return .= '</div>'."\n";
	// END submit box

	$return .= '</div>';
	$return .= '</form>';

	return $return;
}


/* list all addons */
function list_addons() {
	$addons = array();
	$path = BT_ROOT.DIR_ADDONS;

	if (is_dir($path)) {
		// get the list of installed addons
		$addons_list = rm_dots_dir(scandir($path));

		// include the addons
		foreach ($addons_list as $addon) {
			$inc = sprintf('%s/%s/%s.php', $path, $addon, $addon);
			$is_enabled = !is_file(sprintf('%s/%s/.disabled', $path, $addon));
			if (is_file($inc)) {
				$addons[$addon] = $is_enabled;
				include_once $inc;
			}
		}
	}

	return $addons;
}


function addon_get_translation($info) {
	if (is_array($info)) {
		return $info[$GLOBALS['lang']['id']];
	}
	return $info;
}

function afficher_liste_modules($tableau, $filtre) {
	if (!empty($tableau)) {
		$out = '<ul id="modules">'."\n";
		foreach ($tableau as $i => $addon) {
			$params = addon_get_conf( $addon['tag'] );

			// DESCRIPTION
			$out .= "\t".'<li>'."\n";
			// CHECKBOX POUR ACTIVER
			$out .= "\t\t".'<span><input type="checkbox" class="checkbox-toggle" name="module_'.$i.'" id="module_'.$i.'" '.(($addon['status']) ? 'checked' : '').' onchange="activate_mod(this);" /><label for="module_'.$i.'"></label></span>'."\n";

			// NOM DU MODULE
			$out .= "\t\t".'<span><a href="modules.php?addon_id='.$addon['tag'].'">'.addon_get_translation($addon['name']).'</a></span>'."\n";

			// VERSION
			$out .= "\t\t".'<span>'.$addon['version'].'</span>'."\n";

			$out .= "\t".'</li>'."\n";

			// AUTRES INFOS
			$url = '';
			$out .= "\t".'<div>'."\n";
			$out .= "\t\t".'<p><code title="'.$GLOBALS['lang']['label_code_theme'].'">'.'{addon_'.$addon['tag'].'}'.'</code>'.addon_get_translation($addon['desc']).'</p>'."\n";
			$out .= "\t\t".'<p>';
			if (is_array( $params ) && count( $params ) > 0){
				$out .= '<a href="module.php?addonName='. $addon['tag'] .'">params</a>';
			}
			if (!empty($addon['url'])) {
				$out .= ' <a href="'.$addon['url'].'">'.$GLOBALS['lang']['label_owner_url'].'</a>';
			}
			$out .= '</p>'."\n";
			$out .= '</div>'."\n";
		}
		$out .= '</ul>'."\n\n";
	} else {
		$out = info($GLOBALS['lang']['note_no_module']);
	}

	echo $out;
}

// TODO: at the end, put this in "afficher_form_filtre()"
function afficher_form_filtre_modules($filtre) {
	$ret = '<div id="form-filtre">'."\n";
	$ret .= '<form method="get" action="'.basename($_SERVER['SCRIPT_NAME']).'" onchange="this.submit();">'."\n";
	$ret .= "\n".'<select name="filtre">'."\n" ;
	// TOUS
	$ret .= '<option value="all"'.($filtre == '' ? ' selected="selected"' : '').'>'.$GLOBALS['lang']['label_all'].'</option>'."\n";
	// ACTIVÉS
	$ret .= '<option value="enabled"'.($filtre == 'enabled' ? ' selected="selected"' : '').'>'.$GLOBALS['lang']['label_enabled'].'</option>'."\n";
	// DÉSACTIVÉS
	$ret .= '<option value="disabled"'.($filtre == 'disabled' ? ' selected="selected"' : '').'>'.$GLOBALS['lang']['label_disabled'].'</option>'."\n";
	$ret .= '</select> '."\n\n";
	$ret .= '</form>'."\n";
	$ret .= '</div>'."\n";
	echo $ret;
}

function traiter_form_module($module) {
	$erreurs = array();
	$path = BT_ROOT.DIR_ADDONS;
	$check_file = sprintf('%s/%s/.disabled', $path, $module['addon_id']);
	$is_enabled = !is_file($check_file);
	$new_status = (bool) $module['status'];

	if ($is_enabled != $new_status) {
		if ($new_status) {
			// Module activé, on supprimer le fichier .disabled
			if (unlink($check_file) === FALSE) {
				$erreurs[] = sprintf($GLOBALS['lang']['err_addon_enabled'], $module['addon_id']);
			}
		} else {
			// Module désactivé, on crée le fichier .disabled
			if (fichier_module_disabled($check_file) === FALSE) {
				$erreurs[] = sprintf($GLOBALS['lang']['err_addon_disabled'], $module['addon_id']);
			}
		}
	}

	if (isset($_POST['mod_activer']) ) {
		if (empty($erreurs)) {
			die('Success'.new_token());
		}
		else {
			die ('Error'.new_token().implode("\n", $erreurs));
		}
	}

	return $erreurs;
}

function init_post_module() {
	return array (
		'addon_id' => htmlspecialchars($_POST['addon_id']),
		'status' => (isset($_POST['statut']) and $_POST['statut'] == 'on') ? '1' : '0',
	);
}
