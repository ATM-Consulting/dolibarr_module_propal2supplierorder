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
 * \file    class/actions_propal2supplierorder.class.php
 * \ingroup propal2supplierorder
 * \brief   This file is an example hook overload class file
 *          Put some comments here
 */
require_once __DIR__ . '/../backport/v19/core/class/commonhookactions.class.php';

/**
 * Class ActionsPropal2SupplierOrder
 */
class ActionsPropal2SupplierOrder extends \propal2supplierorder\RetroCompatCommonHookActions
{
	/**
	 * @var array Hook results. Propagated to $hookmanager->resArray for later reuse
	 */
	public $results = array();

	/**
	 * @var string String displayed by executeHook() immediately after return
	 */
	public $resprints;

	/**
	 * @var array Errors
	 */
	public $errors = array();

	/**
	 * Constructor
	 */
	public function __construct()
	{
	}

	/**
	 * Overloading the doActions function : replacing the parent's function with the one below
	 *
	 * @param   array()         $parameters     Hook metadatas (context, etc...)
	 * @param   CommonObject    &$object        The object to process (an invoice if you are in invoice module, a propale in propale's module, etc...)
	 * @param   string          &$action        Current action (if set). Generally create or edit or null
	 * @param   HookManager     $hookmanager    Hook manager propagated to allow calling another hook
	 * @return  int                             < 0 on error, 0 on success, 1 to replace standard code
	 */
	function addMoreActionsButtons($parameters, &$object, &$action, $hookmanager)
	{
		$error = 0; // Error counter
		global $langs,$conf,$user,$db;
		$element = $object->element;
		$langs->load('propal2supplierorder@propal2supplierorder');
		if (in_array('propalcard', explode(':', $parameters['context'])))
		{
			if($object->statut !=0 && !empty($object->lines) && $user->hasRight('fournisseur', 'facture', 'creer'))
			{
				if (getDolGlobalString('PROPAL2SUPPLIERORDER_TYPE_DOC') == 'propal' || getDolGlobalString('PROPAL2SUPPLIERORDER_TYPE_DOC') == 'both')
				{
					print '
						<div class="inline-block divButAction">
							<a href="'.dol_buildpath('/propal2supplierorder/ventil.php?fk_object='.$object->id.'&object_type='.$element,1).'" class="butAction">'.$langs->trans('ConvertToSupplierOrder').'</a>
						</div>
					';
				}
			}
		}
		elseif (in_array('ordercard', explode(':', $parameters['context'])))
		{
			if($object->statut !=0 && !empty($object->lines) && $user->hasRight('fournisseur', 'facture', 'creer'))
			{
				if (getDolGlobalString('PROPAL2SUPPLIERORDER_TYPE_DOC') == 'order' || getDolGlobalString('PROPAL2SUPPLIERORDER_TYPE_DOC') == 'both')
				{
					print '
						<div class="inline-block divButAction">
							<a href="'.dol_buildpath('/propal2supplierorder/ventil.php?fk_object='.$object->id.'&object_type='.$element,1).'" class="butAction">'.$langs->trans('ConvertToSupplierOrder').'</a>
						</div>
					';
				}
			}
		}
		

		if (! $error)
		{
			return 0; // or return 1 to replace standard code
		}
		else
		{
			$this->errors[] = 'Error message';
			return -1;
		}
	}
}