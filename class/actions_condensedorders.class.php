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

class ActionsCondensedOrders {

    /** 
     * Overloading the addMoreActions function
     * @param   parameters      meta data of the hook
     * @param   object          the object you want to process
     * @param   action          current action
     * @return  int             -1 to throw an error, 0 if no error
     */
    public function addMoreMassActions($parameters, $object, $action = 'create'){
        global $arrayofaction, $langs;

        $label = img_picto('', 'pdf', 'style="color:purple"').$langs->trans("CreateCondensedOrders");
        $label_table = img_picto('', 'pdf', 'style="color:orange"').$langs->trans("CreateCondensedTable");

        $this->resprints = '<option value="CREATE_CONDENSED_ORDERS" data-html="'. dol_escape_htmltag($label) .'"> '. $label .'</option>';
        $this->resprints.= '<option value="CREATE_CONDENSED_TABLE" data-html="'. dol_escape_htmltag($label_table) .'"> '. $label_table .'</option>';
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


        if (GETPOST('massaction') == 'CREATE_CONDENSED_ORDERS' || GETPOST('massaction') == 'CREATE_CONDENSED_TABLE'){
            // PDF Generation
            $arrayOrder = GETPOST("toselect", "array");
            $arrayLineExpe = array();
            $arrayLineProduct = array();
            if(count($arrayOrder) > 0){
                foreach ($arrayOrder as $key => $value){
                    $expe = new Expedition($db);
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
                    $expe = new Expedition($db);
                    $expe->fetch($value);
                    // $arrayOrder[$key] = (int) $value;
                    // var_dump($expe->socid);
                    foreach ($expe->lines as $key => $line){
                        if ($line->fk_product > 0 && !$line->product_type){
                            if(!isset($arrayLineProduct[$line->fk_product])){
                                $arrayLineProduct[$line->fk_product] = array(
                                    'ref' => $line->product_ref,
                                    'qte_det' => array(),
                                    'qte_tot' => $arrayLineExpe[$line->fk_product]['qty']
                                );
                                $arrayLineProduct[$line->fk_product]['qte_det'][0] = array('soc' => $expe->socid, 'qte_expe' => $line->qty, 'ref_expe' => $expe->ref);
                            } else {
                                array_push($arrayLineProduct[$line->fk_product]['qte_det'], array('soc' => $expe->socid, 'qte_expe' => $line->qty, 'ref_expe' => $expe->ref));
                                // $arrayLineProduct[$line->fk_product]['qte_det'][$i] = array('soc' => $expe->socid, 'qte_expe' => $line->qty, 'ref_expe' => $expe->ref);
                            }
                        }
                    }
                }

                if (GETPOST('massaction') == 'CREATE_CONDENSED_TABLE'){
                    $soc = new Societe($db);
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
        //$outputlangs->setDefaultLang($newlang);
        $obj_tmp = new modCondensedOrders($db);

        if (GETPOST('massaction') == 'CREATE_CONDENSED_ORDERS' || GETPOST('massaction') == 'CREATE_CONDENSED_TABLE'){
            // PDF Generation
            $arrayOrder = GETPOST("toselect", "array");
            // PDF Generation
            $arrayOrder = GETPOST("toselect", "array");
            $arrayLineExpe = array();
            $arrayLineProduct = array();
            if(count($arrayOrder) > 0){
                foreach ($arrayOrder as $key => $value){
                    $expe = new Expedition($db);
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
                    $expe = new Expedition($db);
                    $expe->fetch($value);
                    // $arrayOrder[$key] = (int) $value;
                    // var_dump($expe->socid);
                    foreach ($expe->lines as $key => $line){
                        if ($line->fk_product > 0 && !$line->product_type){
                            if(!isset($arrayLineProduct[$line->fk_product])){
                                $arrayLineProduct[$line->fk_product] = array(
                                    'ref' => $line->product_ref,
                                    'qte_det' => array(),
                                    'qte_tot' => $arrayLineExpe[$line->fk_product]['qty']
                                );
                                $arrayLineProduct[$line->fk_product]['qte_det'][0] = array('soc' => $expe->socid, 'qte_expe' => $line->qty, 'ref_expe' => $expe->ref);
                            } else {
                                array_push($arrayLineProduct[$line->fk_product]['qte_det'], array('soc' => $expe->socid, 'qte_expe' => $line->qty, 'ref_expe' => $expe->ref));
                                // $arrayLineProduct[$line->fk_product]['qte_det'][$i] = array('soc' => $expe->socid, 'qte_expe' => $line->qty, 'ref_expe' => $expe->ref);
                            }
                        }
                    }
                }
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
            dol_include_once('/condensedorders/class/condensedorders.class.php');
            if (GETPOST('massaction') == 'CREATE_CONDENSED_ORDERS'){
                $obj = new CondensedOrders($db);
                $obj->model_pdf = 'brahe';
                // $obj->lines = $arrayLineOrder;
                $obj->products = $arrayLineProduct;
                //print 'modele : '.$obj_tmp->model_pdf.'\n lignes : '.$obj_tmp->lines.'\nproduits : '.$obj_tmp->products;
                $result = $obj->generateDocument($obj->model_pdf, $outputlangs, $hidedetails, $hidedesc, $hideref);
                //print $obj_tmp;
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