<?php

	require 'config.php';
	
	dol_include_once('/comm/propal/class/propal.class.php');
	dol_include_once('/commande/class/commande.class.php');
	dol_include_once('/fourn/class/fournisseur.product.class.php');
	
	$langs->load('propal2supplierorder@propal2supplierorder');
	
	llxHeader();
	
	dol_fiche_head();

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
	
	
	
	dol_fiche_end();
	
	llxFooter();

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
			
			if($line->product_type != 0 && $line->product_type != 1) continue;
			
			if($line->fk_product>0) {
				$p=new ProductFournisseur($db);
				$p->fetch($line->fk_product);
				
				if(empty($line->pa)) {
					$pa = _getPrice($p,$fk_supplier,$line->qty);	
				}
				else{
					$pa = $line->pa;
				}
				
				$product_label=$p->getNomUrl(1);
			}
			else{
				$product_label = $line->desc;
				$pa = $line->pa;
			}
			echo $formCore->hidden('TLine['.$k.'][fk_product]', $line->fk_product);
			
			echo '<tr>
				<td>'.$product_label.'</td>
				<td align="right">'.price($line->qty).'</td>
				<td align="right">'.$formCore->texte('', 'TLine['.$k.'][pa]', price($pa), 5,50).'</td>
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
	}

	function _supplier_choice($fk_object,$object_type) {
		global $conf,$user,$db,$langs,$form;
		
		$formCore=new TFormCore('auto','formsupplier','get');
		echo $form->select_thirdparty_list(-1,'fk_supplier',' s.fournisseur=1 ',0);
		echo $formCore->hidden('fk_object', $fk_object);
		echo $formCore->hidden('object_type', $object_type);
		echo $formCore->btsubmit($langs->trans('Ok'), 'bt_choice');
		$formCore->end();
		
		
	}
