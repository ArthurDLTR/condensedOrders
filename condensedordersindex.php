<?php
/* Copyright (C) 2001-2005 Rodolphe Quiedeville <rodolphe@quiedeville.org>
 * Copyright (C) 2004-2015 Laurent Destailleur  <eldy@users.sourceforge.net>
 * Copyright (C) 2005-2012 Regis Houssin        <regis.houssin@inodbox.com>
 * Copyright (C) 2015      Jean-François Ferry	<jfefe@aternatik.fr>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

/**
 *	\file       condensedorders/condensedordersindex.php
 *	\ingroup    condensedorders
 *	\brief      Home page of condensedorders top menu
 */

// Load Dolibarr environment
$res = 0;
// Try main.inc.php into web root known defined into CONTEXT_DOCUMENT_ROOT (not always defined)
if (!$res && !empty($_SERVER["CONTEXT_DOCUMENT_ROOT"])) {
	$res = @include $_SERVER["CONTEXT_DOCUMENT_ROOT"]."/main.inc.php";
}
// Try main.inc.php into web root detected using web root calculated from SCRIPT_FILENAME
$tmp = empty($_SERVER['SCRIPT_FILENAME']) ? '' : $_SERVER['SCRIPT_FILENAME']; $tmp2 = realpath(__FILE__); $i = strlen($tmp) - 1; $j = strlen($tmp2) - 1;
while ($i > 0 && $j > 0 && isset($tmp[$i]) && isset($tmp2[$j]) && $tmp[$i] == $tmp2[$j]) {
	$i--;
	$j--;
}
if (!$res && $i > 0 && file_exists(substr($tmp, 0, ($i + 1))."/main.inc.php")) {
	$res = @include substr($tmp, 0, ($i + 1))."/main.inc.php";
}
if (!$res && $i > 0 && file_exists(dirname(substr($tmp, 0, ($i + 1)))."/main.inc.php")) {
	$res = @include dirname(substr($tmp, 0, ($i + 1)))."/main.inc.php";
}
// Try main.inc.php using relative path
if (!$res && file_exists("../main.inc.php")) {
	$res = @include "../main.inc.php";
}
if (!$res && file_exists("../../main.inc.php")) {
	$res = @include "../../main.inc.php";
}
if (!$res && file_exists("../../../main.inc.php")) {
	$res = @include "../../../main.inc.php";
}
if (!$res) {
	die("Include of main fails");
}

require_once DOL_DOCUMENT_ROOT.'/core/class/html.formfile.class.php';
require_once DOL_DOCUMENT_ROOT.'/expedition/class/expedition.class.php';
require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';
require_once DOL_DOCUMENT_ROOT.'/commande/class/commande.class.php';

// Load translation files required by the page
$langs->loadLangs(array("condensedorders@condensedorders"));

$action = GETPOST('action', 'aZ09');

$now = dol_now();
$max = getDolGlobalInt('MAIN_SIZE_SHORTLIST_LIMIT', 5);

// Security check - Protection if external user
$socid = GETPOST('socid', 'int');
if (isset($user->socid) && $user->socid > 0) {
	$action = '';
	$socid = $user->socid;
}

// Security check (enable the most restrictive one)
//if ($user->socid > 0) accessforbidden();
//if ($user->socid > 0) $socid = $user->socid;
//if (!isModEnabled('condensedorders')) {
//	accessforbidden('Module not enabled');
//}
//if (! $user->hasRight('condensedorders', 'myobject', 'read')) {
//	accessforbidden();
//}
//restrictedArea($user, 'condensedorders', 0, 'condensedorders_myobject', 'myobject', '', 'rowid');
//if (empty($user->admin)) {
//	accessforbidden('Must be admin');
//}


/*
 * Actions
 */

// None

/*
 * View
 */

$form = new Form($db);
$formfile = new FormFile($db);
$comm = new Commande($db);
$prod = new Product($db);
$expe = new Expedition($db); 

llxHeader("", $langs->trans("CondensedOrdersArea"), '', '', 0, 0, '', '', '', 'mod-condensedorders page-index');

print load_fiche_titre($langs->trans("CondensedOrdersArea"), '', 'condensedorders.png@condensedorders');

print '<div class="fichecenter"><div class="fichethirdleft">';



// Draft MyObject
if (True) {
	$langs->load("orders");

	$sql = "SELECT e.rowid as expe_id, e.ref as expe_ref, e.fk_statut as expe_statut, p.rowid as prod_id, p.ref as prod_ref, p.description as prod_descr, p.label as prod_label, p.tobuy as prod_tobuy, p.tosell as prod_tosell, p.entity as prod_entity, ed.qty as ed_qty, c.rowid as comm_id, c.ref as comm_ref, c.fk_statut as comm_statut";
	$sql.= " FROM ".MAIN_DB_PREFIX."expeditiondet as ed";
	$sql.= " LEFT JOIN ".MAIN_DB_PREFIX."expedition as e ON e.rowid = ed.fk_expedition";
	$sql.= " LEFT JOIN ".MAIN_DB_PREFIX."commandedet as cd ON cd.rowid = ed.fk_elementdet";
	$sql.= " LEFT JOIN ".MAIN_DB_PREFIX."commande as c ON cd.fk_commande = c.rowid";
	$sql.= " LEFT JOIN ".MAIN_DB_PREFIX."product as p ON p.rowid = cd.fk_product";
	$sql.= " WHERE e.fk_statut = 1";
	$sql.= " ORDER BY p.rowid DESC";

	$resql = $db->query($sql);
	if ($resql)
	{
		$total = 0;
		$num = $db->num_rows($resql);

		print '<table class="noborder centpercent">';
		print '<tr class="liste_titre">';
		print '<th class="nowrap">'.$langs->trans("EXPEDITION_REF").($num?'<span class="badge marginleftonlyshort">'.$num.'</span>':'').'</th>';
		print '<th class="nowrap">'.$langs->trans("PRODUCT_REF").($num?'<span class="badge marginleftonlyshort">'.$num.'</span>':'').'</th>';
		print '<th class="nowrap">'.$langs->trans("PRODUCT_LABEL").($num?'<span class="badge marginleftonlyshort">'.$num.'</span>':'').'</th>';
		print '<th class="nowrap">'.$langs->trans("QTY").($num?'<span class="badge marginleftonlyshort">'.$num.'</span>':'').'</th>';
		print '<th class="nowrap">'.$langs->trans("QTY_TOTALE").($num?'<span class="badge marginleftonlyshort">'.$num.'</span>':'').'</th>';

		print '</tr>';

		if ($num > 0)
		{
			$i = 0;
			$obj = $db->fetch_object($resql);
			while ($i < $num)
			{

				
				$expe->id = $obj->expe_id;
				$expe->ref = $obj->expe_ref;
				$expe->statut = $obj->expe_statut;

				$prod->id = $obj->prod_id;
				$prod->ref = $obj->prod_ref;
				$prod->description = $obj->prod_descr;
				$prod->label = $obj->prod_label;
				$prod->status_buy = $obj->prod_tobuy;
				$prod->status = $obj->prod_tosell;
				$prod->entity = $obj->prod_entity;

				$comm->id = $obj->comm_id;
				$comm->ref = $obj->comm_ref;
				$comm->statut = $obj->comm_statut;

				print '<tr class="oddeven">';
				// Case pour l'expédition avec nom cliquable
				print '<td class="nowrap" data-ker="ref">' . $expe->getNomUrl(1, '', 100, 0, 1, 1) . '</td>';
				// Case pour le produit avec nom cliquable
				print '<td class="nowrap">' . $prod->getNomUrl(1) . '</td>';
				// Case avec le nom du produit (peut-être pas utile)
				print '<td class="tdoverflowmax200">' . $obj->prod_label . '</td>';
				// Boucle pour afficher les quantités du produit dans chaque commande
				print '<td class="tdoverflowmax200">';
				$qty = 0;
				while($prod->id == $obj->prod_id && $i < $num){
					// On affiche la quantité dans chaque commande concerné avec lien cliquable vers la commande
					print '<div>'. $obj->ed_qty .' venant de '. $comm->getNomUrl(1, '', 100, 0, 1, 1) .'</div>';
					// On passe à la commande suivante et on met à jour la commande pour que le lien change aussi
					$comm->id = $obj->comm_id;
					$comm->ref = $obj->comm_ref;
					$comm->statut = $obj->comm_statut;
					$i++;
					$qty+= $obj->ed_qty; // Calcul de la quantité totale du produit concerné
					$obj = $db->fetch_object($resql);
				}
				print '</td>';
				// Colonne pour afficher la quantité totale du produit
				print '<td class="nowrap">' . $qty . '</td>';
			}
		}
		else
		{

			print '<tr class="oddeven"><td colspan="3" class="opacitymedium">'.$langs->trans("NoOrder").'</td></tr>';
		}
		print "</table><br>";

		$db->free($resql);
	}
	else
	{
		dol_print_error($db);
	}
}


print '</div><div class="fichetwothirdright">';


/* BEGIN MODULEBUILDER LASTMODIFIED MYOBJECT
// Last modified myobject
if (isModEnabled('condensedorders') && $user->hasRight('condensedorders', 'read')) {
	$sql = "SELECT s.rowid, s.ref, s.label, s.date_creation, s.tms";
	$sql.= " FROM ".MAIN_DB_PREFIX."condensedorders_myobject as s";
	$sql.= " WHERE s.entity IN (".getEntity($myobjectstatic->element).")";
	//if ($socid)	$sql.= " AND s.rowid = $socid";
	$sql .= " ORDER BY s.tms DESC";
	$sql .= $db->plimit($max, 0);

	$resql = $db->query($sql);
	if ($resql)
	{
		$num = $db->num_rows($resql);
		$i = 0;

		print '<table class="noborder centpercent">';
		print '<tr class="liste_titre">';
		print '<th colspan="2">';
		print $langs->trans("BoxTitleLatestModifiedMyObjects", $max);
		print '</th>';
		print '<th class="right">'.$langs->trans("DateModificationShort").'</th>';
		print '</tr>';
		if ($num)
		{
			while ($i < $num)
			{
				$objp = $db->fetch_object($resql);

				$myobjectstatic->id=$objp->rowid;
				$myobjectstatic->ref=$objp->ref;
				$myobjectstatic->label=$objp->label;
				$myobjectstatic->status = $objp->status;

				print '<tr class="oddeven">';
				print '<td class="nowrap">'.$myobjectstatic->getNomUrl(1).'</td>';
				print '<td class="right nowrap">';
				print "</td>";
				print '<td class="right nowrap">'.dol_print_date($db->jdate($objp->tms), 'day')."</td>";
				print '</tr>';
				$i++;
			}

			$db->free($resql);
		} else {
			print '<tr class="oddeven"><td colspan="3" class="opacitymedium">'.$langs->trans("None").'</td></tr>';
		}
		print "</table><br>";
	}
}
*/

print '</div></div>';

// End of page
llxFooter();
$db->close();
