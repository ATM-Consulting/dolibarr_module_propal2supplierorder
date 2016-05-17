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
 *	\file		lib/propal2supplierorder.lib.php
 *	\ingroup	propal2supplierorder
 *	\brief		This file is an example module library
 *				Put some comments here
 */

function propal2supplierorderAdminPrepareHead()
{
    global $langs, $conf;

    $langs->load("propal2supplierorder@propal2supplierorder");

    $h = 0;
    $head = array();

    $head[$h][0] = dol_buildpath("/propal2supplierorder/admin/propal2supplierorder_setup.php", 1);
    $head[$h][1] = $langs->trans("Parameters");
    $head[$h][2] = 'settings';
    $h++;
    $head[$h][0] = dol_buildpath("/propal2supplierorder/admin/propal2supplierorder_about.php", 1);
    $head[$h][1] = $langs->trans("About");
    $head[$h][2] = 'about';
    $h++;

    // Show more tabs from modules
    // Entries must be declared in modules descriptor with line
    //$this->tabs = array(
    //	'entity:+tabname:Title:@propal2supplierorder:/propal2supplierorder/mypage.php?id=__ID__'
    //); // to add new tab
    //$this->tabs = array(
    //	'entity:-tabname:Title:@propal2supplierorder:/propal2supplierorder/mypage.php?id=__ID__'
    //); // to remove a tab
    complete_head_from_modules($conf, $langs, $object, $head, $h, 'propal2supplierorder');

    return $head;
}

/**
 * ATTENTION fonction copié/collé de multidevise/script/interface.php
 */
function _getcurrencyrate(&$ATMdb,$currency_code){
	global $conf;
	
	$sql = 'SELECT cr.rate
			FROM '.MAIN_DB_PREFIX.'currency_rate as cr
				LEFT JOIN '.MAIN_DB_PREFIX.'currency as c ON (c.rowid = cr.id_currency)
			WHERE c.code = "'.$currency_code.'" AND cr.id_entity = '.$conf->entity.'
				ORDER BY cr.dt_sync DESC LIMIT 1';
	
	$ATMdb->Execute($sql);
	$ATMdb->Get_line();
	
	$Tres["currency_rate"] = round($ATMdb->Get_field('rate'),$conf->global->MAIN_MAX_DECIMALS_UNIT);
	
	return $Tres;
}