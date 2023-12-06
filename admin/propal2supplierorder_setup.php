<?php
/* <one line to give the program's name and a brief idea of what it does.>
 * Copyright (C) 2015 ATM Consulting <support@atm-consulting.fr>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

/**
 * 	\file		admin/propal2supplierorder.php
 * 	\ingroup	propal2supplierorder
 * 	\brief		This file is an example module setup page
 * 				Put some comments here
 */
// Dolibarr environment
$res = @include("../../main.inc.php"); // From htdocs directory
if (! $res) {
    $res = @include("../../../main.inc.php"); // From "custom" directory
}

// Libraries
require_once DOL_DOCUMENT_ROOT . "/core/lib/admin.lib.php";
require_once '../lib/propal2supplierorder.lib.php';

// Translations
$langs->load("propal2supplierorder@propal2supplierorder");

// Access control
if (! $user->admin) {
    accessforbidden();
}

// Parameters
$action = GETPOST('action', 'alpha');

/*
 * Actions
 */
if (preg_match('/set_(.*)/',$action,$reg))
{
	$code=$reg[1];
	if (dolibarr_set_const($db, $code, GETPOST($code), 'chaine', 0, '', $conf->entity) > 0)
	{
		header("Location: ".$_SERVER["PHP_SELF"]);
		exit;
	}
	else
	{
		dol_print_error($db);
	}
}

if (preg_match('/del_(.*)/',$action,$reg))
{
	$code=$reg[1];
	if (dolibarr_del_const($db, $code, 0) > 0)
	{
		Header("Location: ".$_SERVER["PHP_SELF"]);
		exit;
	}
	else
	{
		dol_print_error($db);
	}
}

/*
 * View
 */
$page_name = "Propal2SupplierOrderSetup";
llxHeader('', $langs->trans($page_name));

// Subheader
$linkback = '<a href="' . DOL_URL_ROOT . '/admin/modules.php">'
    . $langs->trans("BackToModuleList") . '</a>';
print_fiche_titre($langs->trans($page_name), $linkback);

// Configuration header
$head = propal2supplierorderAdminPrepareHead();
dol_fiche_head(
    $head,
    'settings',
    $langs->trans("Module104009Name"),
    0,
    "propal2supplierorder@propal2supplierorder"
);

// Setup page goes here
$form=new Form($db);
$var=false;
print '<table class="noborder" width="100%">';
print '<tr class="liste_titre">';
print '<td>'.$langs->trans("Parameters").'</td>'."\n";
print '<td align="center" width="20">&nbsp;</td>';
print '<td align="center" width="100">'.$langs->trans("Value").'</td>'."\n";


// Example with a yes / no select
$var=!$var;
print '<tr '.$bc[$var].'>';
print '<td>'.$langs->trans("set_PROPAL2SUPPLIERORDER_TYPE_DOC").'</td>';
print '<td align="center" width="20">&nbsp;</td>';
print '<td align="right" width="300">';
print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="action" value="set_PROPAL2SUPPLIERORDER_TYPE_DOC">';
print $form->selectarray('PROPAL2SUPPLIERORDER_TYPE_DOC', array('propal'=>$langs->trans('Proposal'),'order'=>$langs->trans('Order'),'both'=>$langs->trans('Both')),  getDolGlobalString('PROPAL2SUPPLIERORDER_TYPE_DOC'));
print '<input type="submit" class="button" value="'.$langs->trans("Modify").'">';
print '</form>';
print '</td></tr>';

$var=!$var;
print '<tr '.$bc[$var].'>';
print '<td>'.$langs->trans("PROPAL2SUPPLIERORDER_DISALLOW_IMPORT_LINE_WITH_PRICE_ZERO").'</td>';
print '<td align="center" width="20">&nbsp;</td>';
print '<td align="center" width="300">';
print ajax_constantonoff('PROPAL2SUPPLIERORDER_DISALLOW_IMPORT_LINE_WITH_PRICE_ZERO');
print '</td></tr>';

$var=!$var;
print '<tr '.$bc[$var].'>';
print '<td>'.$langs->trans("PROPAL2SUPPLIERORDER_SELECT_LINE_TO_IMPORT").'</td>';
print '<td align="center" width="20">&nbsp;</td>';
print '<td align="center" width="300">';
print ajax_constantonoff('PROPAL2SUPPLIERORDER_SELECT_LINE_TO_IMPORT');
print '</td></tr>';

print '<script type="text/javascript">
	$(function() {
		$("#set_PROPAL2SUPPLIERORDER_SELECT_LINE_TO_IMPORT").click(function(event) {
			$("#del_PROPAL2SUPPLIERORDER_DISALLOW_IMPORT_LINE_WITH_PRICE_ZERO").trigger("click");
		});

		$("#set_PROPAL2SUPPLIERORDER_DISALLOW_IMPORT_LINE_WITH_PRICE_ZERO").click(function(event) {
			$("#del_PROPAL2SUPPLIERORDER_SELECT_LINE_TO_IMPORT").trigger("click");
		});
	});
</script>';


$var=!$var;
print '<tr '.$bc[$var].'>';
print '<td>'.$langs->trans("PROPAL2SUPPLIERORDER_REDIRECT_ON_CF_IF_EXISTS").'</td>';
print '<td align="center" width="20">&nbsp;</td>';
print '<td align="center" width="300">';
print ajax_constantonoff('PROPAL2SUPPLIERORDER_REDIRECT_ON_CF_IF_EXISTS');
print '</td></tr>';

$var=!$var;
print '<tr '.$bc[$var].'>';
print '<td>'.$langs->trans("PROPAL2SUPPLIERORDER_CAN_CREATE_MULTIPLE_SUPPLIER_ORDERS").'</td>';
print '<td align="center" width="20">&nbsp;</td>';
print '<td align="center" width="300">';
print ajax_constantonoff('PROPAL2SUPPLIERORDER_CAN_CREATE_MULTIPLE_SUPPLIER_ORDERS');
print '</td></tr>';

$var=!$var;
print '<tr '.$bc[$var].'>';
print '<td>'.$langs->trans("PROPAL2SUPPLIER_TAKE_ORIGIN_TVA").'</td>';
print '<td align="center" width="20">&nbsp;</td>';
print '<td align="center" width="300">';
print ajax_constantonoff('PROPAL2SUPPLIER_TAKE_ORIGIN_TVA');
print '</td></tr>';

$var=!$var;
print '<tr '.$bc[$var].'>';
print '<td>'.$langs->trans("PROPAL2SUPPLIERORDER_USE_PU_AS_PA").'</td>';
print '<td align="center" width="20">&nbsp;</td>';
print '<td align="center" width="300">';
print ajax_constantonoff('PROPAL2SUPPLIERORDER_USE_PU_AS_PA');
print '</td></tr>';

$var=!$var;
print '<tr '.$bc[$var].'>';
print '<td>'.$langs->trans("PROPAL2SUPPLIERORDER_SHOW_SUBTOTAL_TITLE").'</td>';
print '<td align="center" width="20">&nbsp;</td>';
print '<td align="center" width="300">';
print ajax_constantonoff('PROPAL2SUPPLIERORDER_SHOW_SUBTOTAL_TITLE');
print '</td></tr>';

$var=!$var;
print '<tr '.$bc[$var].'>';
print '<td>'.$langs->trans("PROPAL2SUPPLIERORDER_CANT_ADD_PRODUCT_ALREDY_ORDERED").'</td>';
print '<td align="center" width="20">&nbsp;</td>';
print '<td align="center" width="300">';
print ajax_constantonoff('PROPAL2SUPPLIERORDER_CANT_ADD_PRODUCT_ALREDY_ORDERED');
print '</td></tr>';


print '</table>';

llxFooter();

$db->close();
