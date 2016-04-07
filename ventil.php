<?php

	require 'config.php';
	
	dol_include_once('/comm/propal/class/propal.class.php');
	dol_include_once('/commande/class/commande.class.php');
	dol_include_once('/fourn/class/fournisseur.product.class.php');
	
	$langs->load('propal2supplierorder@propal2supplierorder');
	
	$object_type = GETPOST('object_type');
	$fk_object = GETPOST('fk_object');

	if($object_type == 'commande') {
		$object=new Commande($db);
	}
	else{
		$object=new Propal($db);
	}
	
	if($object->fetch($fk_object)<=0) exit('ImpossibleToLoadObject');
	
	$fk_supplier = GETPOST('fk_supplier');
	
	if($fk_supplier<=0) _supplier_choice($fk_object,$object_type);
	else if(GETPOST('bt_createOrder')) {
		_create_order($fk_supplier,$object,$fk_object,$object_type);
	}
	else _showVentil($fk_supplier,$object,$fk_object,$object_type);
	
	
	function _create_order($fk_supplier,&$object,$fk_object,$object_type) 
	{
		global $conf,$user,$db,$langs;
		
		dol_include_once('/fourn/class/fournisseur.commande.class.php');
		
		$TError = array();
		
		$commande_fournisseur = new CommandeFournisseur($db);
		$ref = 'CF'.$object->ref;
		
		$res = $db->query("SELECT rowid FROM ".MAIN_DB_PREFIX."commande_fournisseur WHERE ref_supplier='".$ref."'");
		
		if($obj = $db->fetch_object($res)) 
		{
			$commande_fournisseur->fetch($obj->rowid);
			setEventMessages('RefSupplierOrderAleadyExists', null, 'errors');
			
			if ($object_type == 'commande') header('Location:'.dol_buildpath('/commande/card.php?id='.$object->id,1));
			else header('Location:'.dol_buildpath('/comm/propal.php?id='.$object->id,1));
			
			exit;
		}
		else 
		{
			$commande_fournisseur->socid = $fk_supplier;
			$commande_fournisseur->ref_supplier = $ref;
			
			if($commande_fournisseur->create($user)<=0) 
			{
				setEventMessages('ErrorCommandFournCreate', null, 'errors');
				
				if ($object_type == 'commande') header('Location:'.dol_buildpath('/commande/card.php?id='.$object->id,1));
				else header('Location:'.dol_buildpath('/comm/propal.php?id='.$object->id,1));
				
				exit;
			}
			
			$fk_cmd_fourn = $commande_fournisseur->id;
			foreach($_POST['TLine'] as $k=>$data) 
			{
				$status_buy = 1;
				$line = $object->lines[$k];
				$pa = price2num($data['pa']);

				if (!empty($conf->global->PROPAL2SUPPLIERORDER_DISALLOW_IMPORT_LINE_WITH_PRICE_ZERO) && $pa == 0) continue;

				$fourn_ref = '';
				// On tente de récup un prix pour ce produit, ce fournisseur et cette quantité, sinon on le crée
				if (!empty($line->fk_product)) 
				{
					$product = new Product($db);
					$product->fetch($line->fk_product);
					$status_buy = $product->status_buy;
					if (!empty($status_buy)) $fourn_ref = _getFournRef($db, $line, $commande_fournisseur->socid);
				}
				
				if ($status_buy)
				{
					if(empty($fourn_ref)) $fourn_ref = _createTarifFourn($fk_supplier, $line->fk_product, $fourn_ref, $line->qty, $data['pa'], $line->product_ref);
					
					$res = $commande_fournisseur->addline($line->desc, $pa, $line->qty, $line->txtva, $line->txlocaltax1, $line->txlocaltax2, $line->fk_product, (int)$line->fk_fournprice, $fourn_ref, $line->remise_percent, 'HT', 0.0, $line->product_type, $line->info_bits, false, $line->date_start, $line->date_end, $line->array_options, $line->fk_unit);
	
					if (!empty($fourn_ref))
					{
						$fk_line = $commande_fournisseur->rowid; // [PH] Oui je sais ça semble pas logique, mais la fonction addline de dolibarr stock le fk_line dans le rowid de l'objet
						$commande_fournisseur->updateline($fk_line, $line->desc, $pa, $line->qty, $line->remise_percent, $line->txtva); 
					}	
				}
				else {
					$res = 0; // Hors achat
				}
				
				if($res<=0) 
				{
					$TError[] = $langs->trans('WarningLineHasNotAdded', $line->product_ref, $line->qty);
				}
							
			}

			if (!empty($TError)) setEventMessages('', $TError, 'errors');
			
			header('Location:'.dol_buildpath('/fourn/commande/card.php?id='.$fk_cmd_fourn,1));
			exit;
		}

	}
	
	function _getFournRef(&$db, &$line, $fk_fourn)
	{
		$sql = "SELECT pfp.ref_fourn";
		$sql.= " FROM ".MAIN_DB_PREFIX."product_fournisseur_price as pfp";
		$sql.= " WHERE pfp.fk_product = ".$line->fk_product;
		$sql.= " AND pfp.fk_soc = ".$fk_fourn;
		$sql.= " AND pfp.quantity <= ".$line->qty;
		$sql.= " ORDER BY pfp.quantity DESC";
		$sql.= " LIMIT 1";
		
		$resql = $db->query($sql);
		if ($resql && ($row = $db->fetch_object($resql)))
		{
			return $row->ref_fourn;
		}
		
		return '';
	}
	
	function _createTarifFourn($fk_fourn, $fk_product, $ref_fourn, $qty, $price, $product_ref) {
		
		global $db, $user;
		
		if (empty($fk_product)) return true; // Ligne libre
		
		$ref_fourn = $product_ref.'-'.$qty;
		
		$product = new ProductFournisseur($db);
		$product->fetch($fk_product);
		$ret=$product->add_fournisseur($user, $fk_fourn, $ref_fourn, $qty);
		
		if($ret > 0) {
			$f = new Fournisseur($db);
			$f->id = $fk_fourn;
			$ret=$product->update_buyprice($qty, $price, $user, 'HT', $f, $_POST["oselDispo"], $ref_fourn, 20);
			if($ret == 0) return $ref_fourn;
		}
		
	}
	
	function _getPrice(&$p, $fk_supplier, $qty) {
		global $conf,$user,$db,$langs,$form;
		
		$sql = "SELECT ";
        $sql.= " pfp.rowid,pfp.unitprice ";
        $sql.= " FROM ".MAIN_DB_PREFIX."product_fournisseur_price as pfp";
        $sql.= " WHERE 1";
        $sql.= " AND pfp.fk_product = ".$p->id;
        $sql.= " AND pfp.quantity <= ".$qty;
		$sql.=" AND pfp.fk_soc=".$fk_supplier." ORDER BY  pfp.quantity  DESC LIMIT 1";
		
		$res = $db->query($sql);
		if($obj = $db->fetch_object($res)) {
			return $obj->unitprice;
		}
		
		return 0;
		
	}

	function _showVentil($fk_supplier,&$object,$fk_object,$object_type) {
		global $conf,$user,$db,$langs,$form;
		
		
		llxHeader();
	
		dol_fiche_head();
		
		$supplier = new Societe($db);
		$supplier->fetch($fk_supplier);
		echo $supplier->getNomUrl(1);
		
		$formCore=new TFormCore('auto','formventil','post');
		echo $formCore->hidden('fk_object', $fk_object);
		echo $formCore->hidden('fk_supplier', $fk_supplier);
		echo $formCore->hidden('object_type', $object_type);
		
		?>
		<table class="border" width="100%">
			<tr class="liste_titre">
				<td><?php echo $langs->trans('Product') ?></td>
				<td><?php echo $langs->trans('Qty') ?></td>
				<td><?php echo $langs->trans('PA') ?></td>
			</tr>
		<?php
		
		foreach($object->lines as $k=>&$line) {
			$add_warning = false;
			if($line->product_type != 0 && $line->product_type != 1) continue;
			
			$pa_as_input = true;
			
			if($line->fk_product>0) {
				$p=new ProductFournisseur($db);
				$p->fetch($line->fk_product);
			
				if($line->fk_fournprice>0 && $p->fetch_product_fournisseur_price($line->fk_fournprice)>0) {
					$pa_as_input = false;
					$pa = $p->fourn_unitprice;

					echo $formCore->hidden('TLine['.$k.'][fk_fournprice]', $line->fk_fournprice);
				}
				else if(empty($line->pa)) {
					$pa = _getPrice($p,$fk_supplier,$line->qty);
					$product = new Product($db);
					$product->fetch($line->fk_product);
					if (empty($product->status_buy)) $add_warning = true;
				}
				else{
					$pa = $line->pa;
					$add_warning = true;
				}
				
				$product_label=$p->getNomUrl(1);
			}
			else{
				$product_label = $line->desc;
				$pa = $line->pa;
			}
			
			if (!empty($conf->global->PROPAL2SUPPLIERORDER_DISALLOW_IMPORT_LINE_WITH_PRICE_ZERO) && $pa == 0) $add_warning = true;
			
			echo $formCore->hidden('TLine['.$k.'][fk_product]', $line->fk_product);
			
			echo '<tr>
				<td>'.$product_label.'</td>
				<td align="right">'.price($line->qty).'</td>
				<td align="right">'.($add_warning ? img_warning($langs->trans('WarningThisLineCanNotBeAdded')) : '').' '.($pa_as_input ? $formCore->texte('', 'TLine['.$k.'][pa]', price($pa), 5,50) : $formCore->hidden('TLine['.$k.'][pa]', $pa).price($pa)).'</td>
			</tr>';
			
			
			
		}
		
		?>
		</table>
		<div class="tabsAction">
		<?php	
		echo $formCore->btsubmit($langs->trans('CreateSupplierOrder'), 'bt_createOrder');
		
		?>
		</div>
		<?php
		
		$formCore->end();
		
		dol_fiche_end();
		llxFooter();
	}

	function _supplier_choice($fk_object,$object_type) {
		global $conf,$user,$db,$langs;
		
		$langs->load('companies');
		dol_include_once('/core/class/html.form.class.php');
		$form = new Form($db);
		
		llxHeader();
		dol_fiche_head();
		
		$formCore=new TFormCore('auto','formsupplier','get');
		echo $langs->trans('Supplier');
		echo $form->select_thirdparty_list(-1,'fk_supplier',' s.fournisseur=1 ',0);
		echo $formCore->hidden('fk_object', $fk_object);
		echo $formCore->hidden('object_type', $object_type);
		echo $formCore->btsubmit($langs->trans('Ok'), 'bt_choice');
		$formCore->end();
		
		dol_fiche_end();
		llxFooter();
	}
