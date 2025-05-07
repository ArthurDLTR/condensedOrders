<?php
/* 
 * Copyright (C) 2025 Arthur LENOBLE
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
 * 	\defgroup   condensedorders     Module CondensedOrders
 *  \brief      CondensedOrders module descriptor.
 *
 *  \file       htdocs/custom/condensedorders/class/modCondensedOrders.class.php
 *  \ingroup    condensedorders
 *  \brief      Description and activation file for module CondensedOrders
 */

require_once DOL_DOCUMENT_ROOT.'/custom/condensedorders/core/modules/modCondensedOrders.class.php';
require_once DOL_DOCUMENT_ROOT.'/custom/condensedorders/class/CondensedOrders.class.php';
require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';

class ActionsCondensedOrders {
    /**
     * Overloading the getNomUrl function
     * @param   parameters      meta data of the hook
     * @param   object          the current objet you want to process
     * @param   action          current action
     * @return  int             -1 to throw an error, 0 if no error
     */
    public function getNomUrl($parameters, $object, $action){
        global $conf, $langs, $hookmanager;
		include_once DOL_DOCUMENT_ROOT.'/core/lib/product.lib.php';
        // print "Type d'objet est un produit : ".$object->type == Product::TYPE_PRODUCT;
        if($object instanceof Product){
            $option = '';
            $maxlength = 0;
            $save_lastsearch_value = -1;
            $morecss = '';
            $add_label = 0;
            $sep = ' - ';
            $result = '';
            $withpicto = 1;

            $newref = $object->ref;
            if ($maxlength) {
                $newref = dol_trunc($newref, 0, 'middle');
            }
            $params = [
                'id' => $object->id,
                'objecttype' => (isset($object->type) ? ($object->type == 1 ? 'service' : 'product') : $object->element),
                'option' => '',
                'nofetch' => 1,
            ];
            $classfortooltip = 'classfortooltip';
            $dataparams = '';
            if (getDolGlobalInt('MAIN_ENABLE_AJAX_TOOLTIP')) {
                $classfortooltip = 'classforajaxtooltip';
                $dataparams = ' data-params="'.dol_escape_htmltag(json_encode($params)).'"';
                $label = '';
            } else {
                $label = implode($object->getTooltipContentArray($params));
            }

            $label = $parameters['label'].'<br><b>Stock réel : </b>'.$object->stock_reel;
            $notooltip = 0;
            $linkclose = '';
            if (empty($notooltip)) {
                if (getDolGlobalString('MAIN_OPTIMIZEFORTEXTBROWSER')) {
                    $label = $langs->trans("ShowProduct");
                    $linkclose .= ' alt="'.dol_escape_htmltag($label, 1, 1).'"';
                }
                $linkclose .= ($label ? ' title="'.dol_escape_htmltag($label, 1, 1).'"' : ' title="tocomplete"');
                $linkclose .= $dataparams.' class="nowraponall '.$classfortooltip.($morecss ? ' '.$morecss : '').'"';
            } else {
                $linkclose = ' class="nowraponall'.($morecss ? ' '.$morecss : '').'"';
            }

            if ($option == 'supplier' || $option == 'category') {
                $url = DOL_URL_ROOT.'/product/fournisseurs.php?id='.$object->id;
            } elseif ($option == 'stock') {
                $url = DOL_URL_ROOT.'/product/stock/product.php?id='.$object->id;
            } elseif ($option == 'composition') {
                $url = DOL_URL_ROOT.'/product/composition/card.php?id='.$object->id;
            } else {
                $url = DOL_URL_ROOT.'/product/card.php?id='.$object->id;
            }

            if ($option !== 'nolink') {
                // Add param to save lastsearch_values or not
                $add_save_lastsearch_values = ($save_lastsearch_value == 1 ? 1 : 0);
                if ($save_lastsearch_value == -1 && isset($_SERVER["PHP_SELF"]) && preg_match('/list\.php/', $_SERVER["PHP_SELF"])) {
                    $add_save_lastsearch_values = 1;
                }
                if ($add_save_lastsearch_values) {
                    $url .= '&save_lastsearch_values=1';
                }
            }

            $linkstart = '<a href="'.$url.'"';
            $linkstart .= $linkclose.'>';
            $linkend = '</a>';

            $result .= $linkstart;
            if ($withpicto) {
                if ($object->type == Product::TYPE_PRODUCT) {
                    $result .= (img_object(($notooltip ? '' : $label), 'product', 'class="paddingright"', 0, 0, $notooltip ? 0 : 1));
                }
                if ($object->type == Product::TYPE_SERVICE) {
                    $result .= (img_object(($notooltip ? '' : $label), 'service', 'class="paddingright"', 0, 0, $notooltip ? 0 : 1));
                }
            }
            $result .= '<span class="aaa">'.dol_escape_htmltag($newref).'</span>';
            $result .= $linkend;
            if ($withpicto != 2) {
                $result .= (($add_label && $object->label) ? $sep.dol_trunc($object->label, ($add_label > 1 ? $add_label : 0)) : '');
            }

            // print $action; // Affiche le type de page
            $parameters['getnomurl'] = $result;
            // $parameters['label'].'<br><b>Stock réel : </b>'.$object->stock_reel;
            // $this->resprints = $parameters['result'];
            return 0;
        }
        return -1;
    }

    /** 
     * Overloading the addMoreActions function
     * @param   parameters      meta data of the hook
     * @param   object          the object you want to process
     * @param   action          current action
     * @return  int             -1 to throw an error, 0 if no error
     */
    public function addMoreMassActions($parameters, $object, $action = 'create'){
        global $arrayofaction, $langs;
        // var_dump($langs);
        $langs->loadLangs(array("condensedorders@condensedorders"));
        $label = img_picto('', 'pdf', 'style="color:purple"').$langs->trans("CreateCondensedOrders");
        $label_wid = img_picto('', 'pdf', 'style="color:red"').' '.$langs->trans("CreateCondensedWidmann");
        $label_table = img_picto('', 'pdf', 'style="color:orange"').' '.$langs->trans("CreateCondensedTable");
        
        $this->resprints = '<option value="CREATE_CONDENSED_ORDERS" data-html="'. dol_escape_htmltag($label) .'"> '. $label .'</option>';
        $this->resprints.= '<option value="CREATE_CONDENSED_WIDMANN" data-html="'. dol_escape_htmltag($label_wid) .'"> '. $label_wid .'</option>';
        if (getDolGlobalInt('CONDENSEDORDERS_TABLE')){
            $this->resprints.= '<option value="CREATE_CONDENSED_TABLE" data-html="'. dol_escape_htmltag($label_table) .'"> '. $label_table .'</option>';
        }
        return 0;
    }


    /**
     * Overloading the showDocuments function
     * @param   parameters      meta data of the hook
     * @param   object          the object you want to process
     * @param   action          current action
     * @return  void
     */
    
    public function showDocuments($parameters, $object, $action = 'create'){
    //function doActions($parameters, $object, $action = 'create'){
        global $db, $conf, $langs;

        $outputlangs = new Translate("", $conf);
        //$outputlangs->setDefaultLang($newlang);
        $obj_tmp = new modCondensedOrders($db);
        $obj_dist = new CondensedOrders($db);


        if (GETPOST('massaction') == 'CREATE_CONDENSED_ORDERS' || GETPOST('massaction') == 'CREATE_CONDENSED_TABLE' || GETPOST('massaction') == 'CREATE_CONDENSED_WIDMANN'){
            // PDF Generation
            $arrayOrder = GETPOST("toselect", "array");
            $arrayLineExpe = array();
            $arrayLineProduct = array();
            if(count($arrayOrder) > 0){
                foreach ($arrayOrder as $key => $value){
                    switch($parameters['currentcontext']){
                        case 'orderlist':
                            $expe = new Commande($db);
                            break;
                        case 'shipmentlist':
                            $expe = new Expedition($db);
                    }
                    $expe->fetch($value);
                    // print $expe->ref.' : '.count($expe->lines). ' lignes <br>';
                    // $arrayOrder[$key] = (int) $value;
                    // var_dump($expe->socid);
                    foreach ($expe->lines as $key => $line){
                        // print 'Produit : '.$line->product_id.' Ref : '.$line->product_ref.'<br>';
                        if ($line->fk_product > 0 && !$line->product_type){
                            
                            if(!isset($arrayLineExpe[$line->fk_product])){
                                $arrayLineExpe[$line->fk_product] = array(
                                    'ref' => $line->product_ref,
                                    'qty' => $line->qty,
                                    'details' => $expe->socid
                                );
                            } else {
                                $arrayLineExpe[$line->fk_product]['qty'] = $arrayLineExpe[$line->fk_product]['qty'] + $line->qty;
                            }
                            // $arrayLineExpe[$line->fk_product] 
                        }
                    }
                }

                foreach ($arrayOrder as $key => $value){
                    switch($parameters['currentcontext']){
                        case 'orderlist':
                            $expe = new Commande($db);
                            break;
                        case 'shipmentlist':
                            $expe = new Expedition($db);
                    }
                    $expe->fetch($value);
                    // $arrayOrder[$key] = (int) $value;
                    // var_dump($expe->lines->fk_product);
                    foreach ($expe->lines as $key => $line){
                        if ($line->fk_product > 0 && !$line->product_type){
                            if(!isset($arrayLineProduct[$line->fk_product])){
                                $arrayLineProduct[$line->fk_product] = array(
                                    'ref' => $line->product_ref,
                                    'prod_id' => $line->fk_product,
                                    'weight' => $obj_dist->getWeight($line->fk_product),
                                    'tag' => $obj_dist->getTag($line->fk_product),
                                    'qte_det' => array(),
                                    'qte_tot' => $arrayLineExpe[$line->fk_product]['qty']
                                );
                                if (getDolGlobalString('CONDENSEDORDERS_SORT') == 'location'){
                                    $arrayLineProduct[$line->fk_product]['qte_det'][0] = array('soc' => $expe->socid, 'dist' => $obj_dist->getDistance($expe->socid), 'qte_expe' => $line->qty, 'ref_client' => $expe->ref_client);
                                } else {
                                    $arrayLineProduct[$line->fk_product]['qte_det'][0] = array('soc' => $expe->socid, 'qte_expe' => $line->qty, 'ref_client' => $expe->ref_client);
                                }
                            } else {
                                if (getDolGlobalString('CONDENSEDORDERS_SORT') == 'location'){
                                    array_push($arrayLineProduct[$line->fk_product]['qte_det'], array('soc' => $expe->socid, 'dist' => $obj_dist->getDistance($expe->socid), 'qte_expe' => $line->qty, 'ref_client' => $expe->ref_client));
                                    // $arrayLineProduct[$line->fk_product]['qte_det'][$i] = array('soc' => $expe->socid, 'qte_expe' => $line->qty, 'ref_expe' => $expe->ref);
                                } else {
                                    array_push($arrayLineProduct[$line->fk_product]['qte_det'], array('soc' => $expe->socid, 'qte_expe' => $line->qty, 'ref_client' => $expe->ref_client));
                                }
                            }
                        }
                    }
                }

                // Sort the qte_det array inside each line of arrayLineProduct
                if(getDolGlobalString('CONDENSEDORDERS_SORT') == 'location'){
                    foreach ($arrayLineProduct as $key => $line){
                        usort($line['qte_det'], function ($a, $b) { return !strcmp($a['dist'], $b['dist']); });
                        // var_dump($line['qte_det']);
                        // print '<br>';
                    } 
                } else if(getDolGlobalString('CONDENSEDORDERS_SORT') == 'weight'){ // Sort the products' array depending on weight
                    usort($arrayLineProduct, function($a, $b) {return $a['weight'] < $b['weight'];});
                } else if (getDolGlobalString('CONDENSEDORDERS_SORT') == 'tag'){
                    usort($arrayLineProduct, function($a, $b) {return $a['tag'] < $b['tag'];});
                } else { // Or depending on the alphabetical order
                    usort($arrayLineProduct, function($a, $b) {return strcmp($a['ref'], $b['ref']);});
                }

                // Test pour voir si le tableau est bien trié dans l'ordre de poids décroissant
                /*
                foreach ($arrayLineProduct as $key => $line){ 
                    print 'Produit : '. $line['ref'] . ' tag : ' . $line['tag'] .'<br>';
                } 
                    */
                if (GETPOST('massaction') == 'CREATE_CONDENSED_TABLE'){
                    $soc = new Societe($db);
                    $prod = new Product($db);
                    // Affichage du tableau contenant les informations pour chaque produit
                    print '<table class="noborder centpercent">';
                    print '<tr class="liste_titre">
                        <td>Réf. Produit</td>
                        <td>Qté par commande</td>
                        <td>Qté totale</td>
                    </tr>';
                    foreach($arrayLineProduct as $key => $line){
                        $prod->fetch($line['prod_id']);
                        print '<tr>
                            <td width="30%">'.$line['ref'].' - '.$prod->label.'</td>';
                            print '<td>';
                            foreach($line['qte_det'] as $key => $det){
                                $soc->fetch($det['soc']);
                                if (!$det['ref_client']){
                                    print $det['qte_expe'].' pour '.$soc->name.'<br>';
                                }else{
                                    print $det['qte_expe'].' pour '.$soc->name.' ('.$det['ref_client'].')<br>';
                                }
                            }
                            print '</td>';
                            print '<td>'.$line['qte_tot'].'</td>
                        </tr>';
                    }
                    print '</table>';
                }
                // var_dump($arrayLineExpe);
                // $condensedOrders = new CondensedOrders($db);
                // $condensedOrders->generatePDF($arrayOrder);
            }
        }
        

        return 0;
    }

    // Fonction de déclenchement de la génération du pdf
    public function doActions($parameters, $object, $action = 'create')
    {
        
        global $db;
        global $conf;
        global $langs;

        $outputlangs = new Translate("", $conf);
        $outputlangs->setDefaultLang();
        $obj_tmp = new modCondensedOrders($db);

        if (GETPOST('massaction') == 'CREATE_CONDENSED_ORDERS' || GETPOST('massaction') == 'CREATE_CONDENSED_TABLE' || GETPOST('massaction') == 'CREATE_CONDENSED_WIDMANN'){
            // PDF Generation
            $arrayOrder = GETPOST("toselect", "array");
            // PDF Generation
            $arrayOrder = GETPOST("toselect", "array");
            $arrayLineExpe = array();
            $arrayLineProduct = array();
            $context = $parameters['currentcontext'];
            $obj_dist = new CondensedOrders($db);
            if(count($arrayOrder) > 0){
                foreach ($arrayOrder as $key => $value){
                    switch($parameters['currentcontext']){
                        case 'orderlist':
                            $expe = new Commande($db);
                            break;
                        case 'shipmentlist':
                            $expe = new Expedition($db);
                    }
                    // $expe = new Expedition($db);
                    $expe->fetch($value);
                    // print $expe->ref.' : '.count($expe->lines). ' lignes <br>';
                    // $arrayOrder[$key] = (int) $value;
                    // var_dump($expe->socid);
                    foreach ($expe->lines as $key => $line){
                            // print 'Produit : '.$line->product_ref.' Qty : '.$line->qty.'<br>';
                        if ($line->fk_product > 0 && !$line->product_type){
                            
                            if(!isset($arrayLineExpe[$line->fk_product])){
                                $arrayLineExpe[$line->fk_product] = array(
                                    'ref' => $line->product_ref,
                                    'qty' => $line->qty,
                                    'details' => $expe->socid
                                );
                            } else {
                                $arrayLineExpe[$line->fk_product]['qty'] = $arrayLineExpe[$line->fk_product]['qty'] + $line->qty;
                            }
                            // $arrayLineExpe[$line->fk_product] 
                        }
                    }
                }

                foreach ($arrayOrder as $key => $value){
                    switch($parameters['currentcontext']){
                        case 'orderlist':
                            $expe = new Commande($db);
                            break;
                        case 'shipmentlist':
                            $expe = new Expedition($db);
                    }
                    $expe->fetch($value);
                    // $arrayOrder[$key] = (int) $value;
                    // var_dump($expe->lines->fk_product);
                    foreach ($expe->lines as $key => $line){
                        if ($line->fk_product > 0 && !$line->product_type){
                            if(!isset($arrayLineProduct[$line->fk_product])){
                                $arrayLineProduct[$line->fk_product] = array(
                                    'ref' => $line->product_ref,
                                    'prod_id' => $line->fk_product,
                                    'weight' => $obj_dist->getWeight($line->fk_product),
                                    'tag' => $obj_dist->getTag($line->fk_product),
                                    'qte_det' => array(),
                                    'qte_tot' => $arrayLineExpe[$line->fk_product]['qty']
                                );
                                if (getDolGlobalString('CONDENSEDORDERS_SORT') == 'location'){
                                    $arrayLineProduct[$line->fk_product]['qte_det'][0] = array('soc' => $expe->socid, 'dist' => $obj_dist->getDistance($expe->socid), 'qte_expe' => $line->qty, 'ref_client' => $expe->ref_client);
                                } else {
                                    $arrayLineProduct[$line->fk_product]['qte_det'][0] = array('soc' => $expe->socid, 'qte_expe' => $line->qty, 'ref_client' => $expe->ref_client);
                                }
                            } else {
                                if (getDolGlobalString('CONDENSEDORDERS_SORT') == 'location'){
                                    array_push($arrayLineProduct[$line->fk_product]['qte_det'], array('soc' => $expe->socid, 'dist' => $obj_dist->getDistance($expe->socid), 'qte_expe' => $line->qty, 'ref_client' => $expe->ref_client));
                                    // $arrayLineProduct[$line->fk_product]['qte_det'][$i] = array('soc' => $expe->socid, 'qte_expe' => $line->qty, 'ref_expe' => $expe->ref);
                                } else {
                                    array_push($arrayLineProduct[$line->fk_product]['qte_det'], array('soc' => $expe->socid, 'qte_expe' => $line->qty, 'ref_client' => $expe->ref_client));
                                }
                            }
                        }
                    }
                }
            }
            
            // Sort the qte_det array inside each line of arrayLineProduct
            if (getDolGlobalString('CONDENSEDORDERS_SORT') == 'location'){
                foreach ($arrayLineProduct as $key => $line){
                    usort($line['qte_det'], function ($a, $b) { return strcmp($a['dist'], $b['dist']); });
                    // var_dump($line['qte_det']);
                }
            } else if(getDolGlobalString('CONDENSEDORDERS_SORT') == 'weight'){ // Sort the products' array depending on weight
                usort($arrayLineProduct, function($a, $b) {return $a['weight'] < $b['weight'];});
            } else if (getDolGlobalString('CONDENSEDORDERS_SORT') == 'tag'){
                usort($arrayLineProduct, function($a, $b) {return $a['tag'] < $b['tag'];});
            } else { // Or depending on the alphabetical order
                usort($arrayLineProduct, function($a, $b) {return strcmp($a['ref'], $b['ref']);});
            }
                
            if(empty($hidedetails)){
                $hidedetails = (getDolGlobalString('MAIN_GENERATE_DOCUMENTS_HIDE_DETAILS') ? 1 : 0);
            }

            if(empty($hidedesc)){
                $hidedesc = (getDolGlobalString('MAIN_GENERATE_DOCUMENTS_HIDE_DESC') ? 1 : 0);
            }
            if(empty($hideref)){
                $hideref = (getDolGlobalString('MAIN_GENERATE_DOCUMENTS_HIDE_REF') ? 1 : 0);
            }
            if (GETPOST('massaction') == 'CREATE_CONDENSED_ORDERS'){
                $obj = new CondensedOrders($db);
                $obj->model_pdf = 'brahe';
                $obj->context = $context;
                $obj->products = $arrayLineProduct;
                //print 'modele : '.$obj_tmp->model_pdf.'\n lignes : '.$obj_tmp->lines.'\nproduits : '.$obj_tmp->products;
                $result = $obj->generateDocument($obj->model_pdf, $outputlangs, $hidedetails, $hidedesc, $hideref);
                // print 'res : '.$result;
            }
            if (GETPOST('massaction') == 'CREATE_CONDENSED_WIDMANN'){
                $obj = new CondensedOrders($db);
                $obj->model_pdf = 'widmann';
                $obj->context = $context;
                $obj->lines = $arrayLineProduct;
                // print 'modele : '.$obj->model_pdf.'\n lignes : '.count($obj->lines);
                $result = $obj->generateDocument($obj->model_pdf, $outputlangs, $hidedetails, $hidedesc, $hideref);
                // print 'res : '.$result;
            }
            /*
            if (GETPOST('massaction') == 'CREATE_CONDENSED_TABLE'){
                require_once DOL_DOCUMENT_ROOT.'/core/class/hookmanager.class.php';
                
                $soc = new Societe($db);
                $obj = new CondensedOrders($db);
                if (GETPOSTISSET('CSVButton', 'bool')){
                    $obj->toCSV($arrayLineProduct);
                }

                // Affichage entête de titre
                
                $hookmanager = new HookManager($db);
                $hookmanager->initHooks(array('shipmentlist'));
                llxHeader("", 'Condensedarea', '', '', 0, 0, '', '', '', 'mod-condensed page-index');

                // Button to download csv
                // print '<form method="POST" id="searchFormList" action="'. $_SERVER["PHP_SELF"] . ' ">';
                // print '<input type="hidden" name="token" value="'.newToken().'">';
                // print '<input type="hidden" name="massaction" id="massaction" value="CREATE_CONDENSED_TABLE">';
		        // print '<input type="submit" class="butAction" value="'.$langs->trans("CSV").'" name="CSVButton">';
                // print '</form>';

                print_barre_liste($langs->trans('CONDENSED_TABLE'), 0, $_SERVER["PHP_SELF"], '', '', '', '', $num, count($arrayLineProduct), 'dolly', 0, '', '', $limit, 0, 0, 1);
                // Affichage du tableau contenant les informations pour chaque produit
                print '<table class="noborder centpercent">';
                print '<tr class="liste_titre">
                    <td>Réf. Produit</td>
                    <td>Qté par commande</td>
                    <td>Qté totale</td>
                </tr>';
                foreach($arrayLineProduct as $key => $line){
                    print '<tr>
                        <td>'.$line['ref'].'</td>';
                        print '<td>';
                        foreach($line['qte_det'] as $key => $det){
                            $soc->fetch($det['soc']);
                            print $det['qte_expe'].' venant de '.$det['ref_expe'].' pour '.$soc->getNomUrl().'<br>';
                        }
                        print '</td>';
                        print '<td>'.$line['qte_tot'].'</td>
                    </tr>';
                }
                print '</table>';
                // $this->toCSV($arrayLineProduct);
                // exit();
            }
                */
            
        }
    }
}