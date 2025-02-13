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
 *  \file       htdocs/condensedorders/class/modCondensedOrders.class.php
 *  \ingroup    condensedorders
 *  \brief      Description and activation file for module CondensedOrders
 */

class ActionsCondensedOrders {

    /**
     * Overloading the addMoreActions function
     * @param   parameters      meta data of the hook
     * @param   object          the object you want to process
     * @param   action          current action
     * @return  int             -1 to throw an error, 0 if no error
     */
    function addMoreMassActions($parameters, $object, $action = 'create'){
        global $arrayofaction, $langs;

        $label = img_picto('', 'docs', 'class="pictofiwxedwidth"').$langs->trans("CreateCondensedOrders");

        $this->resprints = '<option value="CREATE_CONDENSED_ORDERS" data-html="'. dol_escape_htmltag($label) .'">'. $label .'</option>';

        return 0;
    }


    /**
     * Overloading the showDocuments function
     * @param   parameters      meta data of the hook
     * @param   object          the object you want to process
     * @param   action          current action
     * @return  void
     */
    function showDocuments($parameters, $object, $action = 'create'){
    //function doActions($parameters, $object, $action = 'create'){
        global $db;
        if (GETPOST('massaction') == 'CREATE_CONDENSED_ORDERS'){
            // PDF Generation
            $arrayOrder = GETPOST("toselect", "array");
            if(count($arrayOrder) > 0){
                foreach ($arrayOrder as $key => $value){
                    $order = new Expedition($db);
                    $order->fetch($value);
                    print $order->ref.' : '.count($order->lines). ' lignes <br>';
                    // $arrayOrder[$key] = (int) $value;
                    foreach ($order->lines as $key => $line){
                        print 'Produit : '.$line->product_ref.' Qty : '.$line->qty.'<br>';
                    }
                }

                // $condensedOrders = new CondensedOrders($db);
                // $condensedOrders->generatePDF($arrayOrder);
            }
        }   
    }

    // Fonction de déclenchement de la génération du pdf
    function doActions($parameters, $object, $action = 'create')
    {
        global $db;
        global $conf;

        if (GETPOST('massaction') == 'CREATE_CONDENSED_ORDERS'){
            dol_include_once('/condensedorders/class/condensedorders.class.php');
            $obj = new CondensedOrders($db);
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

        $arrayOrder = GETPOST('toselect', 'array');
        $arrayLineOrder = array();
        $arrayLineProduct = array();
        if (count($arrayOrder) > 0){
            foreach($arrayOrder as $key => $value) {
                $obj_static = new Expedition($db);
            }
        }
    }
}