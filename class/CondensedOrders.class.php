<?php
/* Copyright (C) 2025 Arthur LENOBLE
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
 *  \file       htdocs/condensedorders/core/modules/modCondensedOrders.class.php
 *  \ingroup    condensedorders
 *  \brief      Description and activation file for module CondensedOrders
 */
require_once DOL_DOCUMENT_ROOT.'/core/class/commonobject.class.php';

/**
 *  Description and activation class for module CondensedOrders
 */
class CondensedOrders extends CommonObject {

	/**
	 * @var string 		The type of originating object. Combined with $origin_id, it allows to reload $origin_object
	 * @see fetch_origin()
	 */
	public $origin_type;

	/**
	 * @var int 		The id of originating object. Combined with $origin_type, it allows to reload $origin_object
	 * @see fetch_origin()
	 */
	public $origin_id;

	/**
	 * @var	?CommonObject	Origin object. This is set by fetch_origin() from this->origin_type and this->origin_id.
	 */
	public $origin_object;
	
	/**
	 * @var string name of pdf model
	 */
	public $model_pdf;
    /**
	 * Constructor. Define names, constants, directories, boxes, permissions
	 *
	 * @param DoliDB $db Database handler
	 */
	public function __construct($db)
	{
		global $langs, $conf;

		$this->db = $db;
		$this->origin_type = 'commande';
		$this->origin_id = 1;
    }

	/**
	 *  Create a document onto disk according to template module.
	 *
	 *  @param	    string		$modele			Force the model to using ('' to not force)
	 *  @param		Translate	$outputlangs	object lang to use for translations
	 *  @param      int			$hidedetails    Hide details of lines
	 *  @param      int			$hidedesc       Hide description
	 *  @param      int			$hideref        Hide ref
	 *  @param      null|array  $moreparams     Array to provide more information
	 *  @return     int         				0 if KO, 1 if OK
	 */
	public function generateDocument($modele, $outputlangs, $hidedetails = 0, $hidedesc = 0, $hideref = 0, $moreparams = null)
	{
		$outputlangs->load("products");

		if (!dol_strlen($modele)) {
			$modele = 'brahe';

			if (!empty($this->model_pdf)) {
				$modele = $this->model_pdf;
			} elseif (getDolGlobalString('CONDENSEDORDERS_ADDON_PDF')) {
				$modele = getDolGlobalString('CONDENSEDORDERS_ADDON_PDF');
			}
		}

		$modelpath = "custom/condensedorders/core/modules/condensedorders/doc/";

		$this->fetch_origin();
		// dol_syslog("DEBUG : Génération du document pour préparation de commande");

		return $this->commonGenerateDocument($modelpath, $modele, $outputlangs, $hidedetails, $hidedesc, $hideref, $moreparams);
	}
}