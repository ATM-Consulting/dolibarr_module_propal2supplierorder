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
	$object->fetch_projet();
	
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
		
		if($obj = $db->fetch_object($res) && !$conf->global->PROPAL2SUPPLIERORDER_CAN_CREATE_MULTIPLE_SUPPLIER_ORDERS) 
		{
			$commande_fournisseur->fetch($obj->rowid);
			setEventMessages('RefSupplierOrderAleadyExists', null, 'warnings');
			
			//TODO peut être une redirection sur la commande fourn (on à l'objet chargé juste au dessus)
			if (!empty($conf->global->PROPAL2SUPPLIERORDER_REDIRECT_ON_CF_IF_EXISTS)) header('Location:'.dol_buildpath('/fourn/commande/card.php?id='.$commande_fournisseur->id,1));
			elseif ($object_type == 'commande') header('Location:'.dol_buildpath('/commande/card.php?id='.$object->id,1));
			else header('Location:'.dol_buildpath('/comm/propal.php?id='.$object->id,1));
			
			exit;
		}
		else 
		{
			$supplier = new Fournisseur($db);
			$supplier->fetch($fk_supplier);
			
			$commande_fournisseur->socid = $fk_supplier;
			$commande_fournisseur->ref_supplier = $ref;
			$commande_fournisseur->linked_objects[$object->element] = $object->id;
			
			$commande_fournisseur->cond_reglement_id = $supplier->cond_reglement_supplier_id;
			$commande_fournisseur->mode_reglement_id = $supplier->mode_reglement_supplier_id;
			
			$commande_fournisseur->date_livraison = $object->date_livraison;
			
			if($commande_fournisseur->create($user)<=0) 
			{
				setEventMessages('ErrorCommandFournCreate', null, 'errors');
				
				if ($object_type == 'commande') header('Location:'.dol_buildpath('/commande/card.php?id='.$object->id,1));
				else header('Location:'.dol_buildpath('/comm/propal.php?id='.$object->id,1));
				
				exit;
			}
			
			$commande_fournisseur->set_id_projet($user, $object->projet->id);
			
			if (!empty($conf->multidevise->enabled))
			{
				$rate = GETPOST('multicurrency_tx', 'int');
				if (empty($rate)) $rate = 1;
				// TODO voir si on fait un UPDATE du taux de devise, car le module à dû inserer en base le taux associé au code 
				// (update uniquement si le taux est modifiable sur le fomulaire)
			}
			
			$fk_cmd_fourn = $commande_fournisseur->id;
			$TLine = GETPOST('TLine');
			foreach($TLine as $k=>$data) 
			{
				// TODO voir si on importe les titres et sous-totaux si la conf PROPAL2SUPPLIERORDER_SHOW_SUBTOTAL_TITLE est active
				// $data[subtitle] => value = qty
				// $data[subtitle_desc] => value = desc
				// $data[subtitle] => value = qty
				
				$status_buy = 1;
				$line = $object->lines[$k];
				$pa = price2num($data['pa']);
				if (!empty($conf->multidevise->enabled))
				{
					$pa_devise = price2num($data['pa_devise']);
					if (!empty($pa_devise))
					{
						$pa = $pa_devise / $rate;
					}
					
					$_REQUEST['dp_pu_devise'] = $pa_devise;
					$_REQUEST['qty'] = $line->qty;
					$_REQUEST['buying_price'] = $pa;
				}


				if (!empty($conf->global->PROPAL2SUPPLIERORDER_DISALLOW_IMPORT_LINE_WITH_PRICE_ZERO) && $pa == 0) continue;
				elseif (!empty($conf->global->PROPAL2SUPPLIERORDER_SELECT_LINE_TO_IMPORT) && empty($data['to_import'])) continue;
				
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
					
					$tva = 0;
					if (!empty($conf->global->PROPAL2SUPPLIER_TAKE_ORIGIN_TVA)) $tva = $line->tva_tx;
					
					$res = $commande_fournisseur->addline($line->desc, $pa, $line->qty, $tva, $line->txlocaltax1, $line->txlocaltax2, $line->fk_product, (int)$line->fk_fournprice, $fourn_ref, $line->remise_percent, 'HT', 0.0, $line->product_type, $line->info_bits, false, $line->date_start, $line->date_end, $line->array_options, $line->fk_unit);
	
					if(!empty($conf->nomenclature->enabled)) {
						
						dol_include_once('/nomenclature/class/nomenclature.class.php');
						$n=new TNomenclature;
						$PDOdb = new TPDOdb;
						$n->loadByObjectId($PDOdb, $data['lineid'], $object_type);
						if($n->iExist) {
							$n->reinit();
							$n->fk_object = $commande_fournisseur->rowid;
							$n->object_type = $commande_fournisseur->element;
							$n->save($PDOdb);
						}
						
					}
	
					if (!empty($fourn_ref))
					{
						$fk_line = $commande_fournisseur->rowid; // [PH] Oui je sais ça semble pas logique, mais la fonction addline de dolibarr stock le fk_line dans le rowid de l'objet
						$commande_fournisseur->updateline($fk_line, $line->desc, $pa, $line->qty, $line->remise_percent, $tva); 
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
		
		_showTauxMulticurrency($supplier);
		
		?>
		<table class="border" width="100%">
			<tr class="liste_titre">
				<td><?php echo $langs->trans('Product') ?></td>
				<td align="right"><?php echo $langs->trans('Qty') ?></td>
				<td align="right"><?php echo $langs->trans('PA') ?></td>
				<?php _showTitleMulticurrency(); ?>
				<?php if ($conf->global->PROPAL2SUPPLIERORDER_SELECT_LINE_TO_IMPORT) { ?>
					<td align="center" class="maxwidthsearch"><?php echo $langs->trans('Import'); ?></td>
				<?php } ?>
			</tr>
		<?php
		
		$nb_nbsp = 0;
		foreach($object->lines as $k=>&$line) {
			$add_warning = false;
			
			if ($line->product_type == 9 && !empty($conf->global->PROPAL2SUPPLIERORDER_SHOW_SUBTOTAL_TITLE))
			{
				if ($line->qty <= 10 ) 
				{
					$nb_nbsp = $line->qty - 1;
					$label = !empty($line->label) ? $line->label : $line->desc;
					
					print '<tr class="title title_level_'.$line->qty.'" style="background-color:#ADADCF;" >';
					print '<td><b>'.(str_repeat('&nbsp;&nbsp;&nbsp;&nbsp;', $nb_nbsp)).$label.'</b></td>';
					print '<td><input type="hidden" name="TLine['.$k.'][subtitle]" value="'.($line->qty).'" /></td>';
					print '<td><textarea class="hideobject" name="TLine['.$k.'][subtitle_desc]">'.$label.'</textarea></td>';
					if (!empty($conf->multidevise->enabled)) print '<td></td>';
					if (!empty($conf->global->PROPAL2SUPPLIERORDER_SELECT_LINE_TO_IMPORT)) print '<td align="center"><span style="cursor:pointer;" onclick="checkNextInput(this);">v</span></td>';
					print '</tr>';
				}
				else // sous-total 
				{
					print '<tr class="" style="background-color:#cdcdef;">';
					print '<td>'.(str_repeat('&nbsp;&nbsp;&nbsp;&nbsp;', 99 - $line->qty)).$line->desc.' [niveau : '.(100-$line->qty).']</td>';
					print '<td><input type="hidden" name="TLine['.$k.'][subtotal]" value="'.($line->qty).'" /></td>';
					print '</tr>';
				}
				
				continue;
			}
			elseif($line->product_type != 0 && $line->product_type != 1) continue;
			
			$pa_as_input = true;
			
			if (!empty($conf->global->PROPAL2SUPPLIERORDER_USE_PU_AS_PA))
			{
				$pa = (double) $line->subprice;
				
				if($line->fk_product>0)	
				{
					$p=new ProductFournisseur($db);
					$p->fetch($line->fk_product);
					$product_label=$p->getNomUrl(1);
				}
				else
				{
					$product_label = $line->desc;
				}
			}
			else
			{
				$line_pa = !empty($line->pa_ht) ? $line->pa_ht : $line->pa;
				$line_pa = (double) $line_pa;
				
				if($line->fk_product>0) {
					$p=new ProductFournisseur($db);
					$p->fetch($line->fk_product);
			
					if($line->fk_fournprice>0 && $p->fetch_product_fournisseur_price($line->fk_fournprice)>0) {
						$pa_as_input = false;
						$pa = $p->fourn_unitprice;
	
						echo $formCore->hidden('TLine['.$k.'][fk_fournprice]', $line->fk_fournprice);
					}
					else if(empty($line_pa)) {
						$pa = _getPrice($p,$fk_supplier,$line->qty);
						
						$product = new Product($db);
						$product->fetch($line->fk_product);
						if (empty($product->status_buy)) $add_warning = true;
					}
					else{
						$pa = $line_pa;
						$add_warning = true;
					}
					
					$product_label=$p->getNomUrl(1) .' '.$p->label;
				}
				else{
					$product_label = $line->desc;
					$pa = $line_pa;
				}
			}

			if (!empty($conf->global->PROPAL2SUPPLIERORDER_DISALLOW_IMPORT_LINE_WITH_PRICE_ZERO) && $pa == 0) $add_warning = true;
			
			if (!empty($conf->global->PROPAL2SUPPLIERORDER_SELECT_LINE_TO_IMPORT)) $add_warning = false;
			
			echo '<tr>';
			echo $formCore->hidden('TLine['.$k.'][fk_product]', $line->fk_product);
			echo $formCore->hidden('TLine['.$k.'][lineid]', $line->rowid);
			
			echo '
				<td>'.(str_repeat('&nbsp;&nbsp;&nbsp;&nbsp;', $nb_nbsp)).$product_label.'</td>
				<td align="right">'.price($line->qty).'</td>
				<td align="right" class="td_pa_base">'.($add_warning ? img_warning($langs->trans('WarningThisLineCanNotBeAdded')) : '').' '.($pa_as_input ? $formCore->texte('', 'TLine['.$k.'][pa]', price($pa), 5,50, 'data-k="'.$k.'"') : $formCore->hidden('TLine['.$k.'][pa]', $pa).price($pa)).'</td>';
			
			_showColumnMulticurrency($supplier, $formCore, $pa, $pa_as_input, $k);
			
			if ($conf->global->PROPAL2SUPPLIERORDER_SELECT_LINE_TO_IMPORT)
			{
				echo '<td align="center"><input type="checkbox" class="to_import" name="TLine['.$k.'][to_import]" value="1"/></td>';
			}
			
			echo '</tr>';
		}
		
		?>
		</table>
		<div class="tabsAction">
		<?php	
		
		echo $formCore->btsubmit($langs->trans('CreateSupplierOrder'), 'bt_createOrder');
		
		?>
		</div>
		<?php
		
		if (!empty($conf->global->PROPAL2SUPPLIERORDER_SHOW_SUBTOTAL_TITLE))
		{
			?>
			<script type="text/javascript">
				function checkNextInput(span)
				{
					var i = 0;
					var tr = $(span).closest('tr');
					var cur_tr;
					while (cur_tr = $(tr).next())
					{
						if ($(cur_tr).hasClass('title') || $(cur_tr).length == 0  || $(cur_tr).get(0).tagName != 'TR')
						{
							break;
						}
						
						$(cur_tr).find('input.to_import').trigger('click');
						tr = cur_tr;
					}
				}
			</script>
			<?php
		}
		
		
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

	/**
	 * TODO à faire évoluer si on veux une compatibilité avec multicurrency à partir de la 4.0
	 */
	function _showTauxMulticurrency(&$supplier)
	{
		global $conf,$langs;
		
		if (!empty($conf->multidevise->enabled))
		{
			$langs->loadCacheCurrencies('');
			
			dol_include_once('/multidevise/class/multidevise.class.php');
			dol_include_once('/propal2supplierorder/lib/propal2supplierorder.lib.php');
			
			$PDOdb = new TPDOdb;
			$c = new TMultideviseClient;
			$c->load($PDOdb, $supplier->id);
			
			if (!empty($c->devise_code))
			{
				$TRes = _getcurrencyrate($PDOdb, $c->devise_code);
				$rate = 1;
				if (!empty($TRes['currency_rate'])) $rate = $TRes['currency_rate'];
				
				$supplier->fk_multicurrency = $c->fk_devise;
				$supplier->multicurrency_code = $c->devise_code;
				$supplier->multicurrency_rate = $rate;
				
				// TODO à voir si on utilise un $form->select_currency plutot qu'un input avec le taux et d'utiliser la devise du fournisseur
				echo '<p>
						<label>'.$langs->trans('propal2supplierorder_devisefourn').'</label> <span>'.$langs->cache_currencies[$c->devise_code]['label'] . ' ('. $langs->getCurrencySymbol($c->devise_code).')</span>
						<br />
						<label>'.$langs->trans('propal2supplierorder_txdevisefourn').'</label> <span>'.$rate.'</span>
						<input type="hidden" name="multicurrency_code" value="'.$c->devise_code.'" />
						<!-- input currency nécessaire pour le module multidevise -->
						<input type="hidden" name="currency" value="'.$c->devise_code.'" />
						<input type="hidden" name="fk_multicurrency" value="'.$c->fk_devise.'" />
						<input type="hidden" name="multicurrency_tx" data-rate="'.$rate.'" value="'.$rate.'" />
					</p>';
					
				?>
				<script type="text/javascript">
					$(function() {
						var propal2supplierorder_multicurrency_rate = <?php echo (float) $rate; ?>;
						$("#formventil .multicurrency_input").unbind().change(function(event) {
							var k = $(this).data('k');
							var pa = $(this).val() / propal2supplierorder_multicurrency_rate;
							$("#formventil input[name='TLine["+k+"][pa]']").val(pa);
						});
						
						$("#formventil .td_pa_base input").unbind().change(function(event) {
							var k = $(this).data('k');
							var pa_devise = $(this).val() * propal2supplierorder_multicurrency_rate;
							$("#formventil input[name='TLine["+k+"][pa_devise]']").val(pa_devise);
						});
					});
				</script>
				<?php
			}
			
		}
	}

	function _showTitleMulticurrency()
	{
		global $conf,$langs;
		
		if (!empty($conf->multidevise->enabled))
		{
			echo '<td align="right">'.$langs->trans('propal2supplierorder_pa_devisefourn').'</td>';
		}
	}

	function _showColumnMulticurrency(&$supplier, &$formCore, $pa, $pa_as_input, $k)
	{
		global $conf;
		
		if (!empty($conf->multidevise->enabled))
		{
			$pa_devise = $pa * $supplier->multicurrency_rate;
			echo '<td align="right">'.($pa_as_input ? '<input class="multicurrency_input" data-k="'.$k.'" type="text" name="TLine['.$k.'][pa_devise]" value="'.$pa_devise.'" size="8" />' : price($pa_devise)).'</td>';
		}
	}
