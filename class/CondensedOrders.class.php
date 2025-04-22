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
	 * @var string context of the object
	 */
	public $context;

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

		// $this->fetch_origin();
		// dol_syslog("DEBUG : Génération du document pour préparation de commande");

		return $this->commonGenerateDocument($modelpath, $modele, $outputlangs, $hidedetails, $hidedesc, $hideref, $moreparams);
	}

	/**
	 * Get the distance between the warehouse address and a thirdparty address
	 * 
	 * @param	int			$socid		ID of the thirdparty you want to know the distance
	 * @return	int 					Distance between the two addresses
	 */
    public function getDistance($socid){
		// Getting the coordinates of the origin point 
        // $origin_addr_txt = "13 Route Des Chenevières, 10200 Soulaines-Dhuys, France";
		// $origin_addr = urlencode($origin_addr_txt);
		// $origin_url = "https://api.opencagedata.com/geocode/v1/json?q=".$origin_addr."&key=9841e655d7da433fadb910898385f29a";

		// $ch = curl_init();
		// curl_setopt($ch, CURLOPT_URL, $url);
		// curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		// curl_setopt($ch, CURLOPT_PROXYPORT, 3128);
		// curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
		// curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
		// $response_origin = curl_exec($ch);
		// curl_close($ch);

		// $response_origin_a = json_decode($response_origin);

		$lat_origin = 48.37261565174393;
		$lng_origin = 4.7282770826216876;
		
		// print "latityde obtenue : ".$lat_origin." et longitude : ".$lng_origin."<br>";

		// Getting the coordinates of the destination
		$sql = "SELECT s.address as addr, s.zip as zip, s.town as town";
		$sql.= " FROM ".MAIN_DB_PREFIX."societe as s";
		$sql.= " WHERE s.rowid = ".$socid;

		$result = $this->db->query($sql);
		if ($result){
			$obj = $this->db->fetch_object($result);
		} else {
			$this->db->error();
		}

		$addr_txt = $obj->addr.", ".$obj->zip." ".$obj->town.", France";
		$addr = urlencode($addr_txt);
		$url = "https://api.opencagedata.com/geocode/v1/json?q=".$addr."&key=9841e655d7da433fadb910898385f29a";

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_PROXYPORT, 3128);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
		$response = curl_exec($ch);
		curl_close($ch);

		$response_a = json_decode($response);

		$lat = $response_a->results[0]->geometry->lat;
		$lng = $response_a->results[0]->geometry->lng;
		// var_dump($response_a);
		// print "latityde obtenue : ".$lat." et longitude : ".$lng."<br>";

		return $this->calcDistance($lat_origin, $lng_origin, $lat, $lng);
    }

	/**
	 * Function to calculate the distance between two coordinates
	 * 
	 * @param	int 		$lat_origin		Origin latitude
	 * @param 	int			$lng_origin		Origin longitude
	 * @param	int			$lat_dest		Destination latitude
	 * @param	int			$lng_dest		Destination longitude
	 * 
	 * @return 	int							Distance between the origin and the distance
	 */
	public function calcDistance($lat_origin, $lng_origin, $lat_dest, $lng_dest){
		$theta = $lng_origin - $lng_dest;
		$dist = sin(deg2rad($lat_origin)) * sin(deg2rad($lat_dest)) + cos(deg2rad($lat_origin)) * cos(deg2rad($lat_dest)) * cos(deg2rad($theta));
		$dist = rad2deg(acos($dist));
		return $dist * 60 * 1.1515 * 1.609344;
	}

	/**
	 * Function to get the weight of a product given in parameter
	 * 
	 * @param	int 		$idprod			ID of a product
	 * 
	 * @return	int							Wieght of the product
	 */
	public function getWeight($idprod){
		$sql = "SELECT p.weight as w, p.weight_units as wunits";
		$sql.= " FROM ".MAIN_DB_PREFIX."product as p";
		$sql.= " WHERE p.rowid = ".$idprod;

		$result = $this->db->query($sql);
		if ($result){
			$obj = $this->db->fetch_object($result);
		} else {
			$this->db->error();
		}

		// print "Poids de ". $idprod ." : ". $obj->w ." unités : ".$obj->wunits;
		// print "Poids calculé : " . $obj->w * (10 ** $obj->wunits). "<br>";
		return $obj->w * (10 ** $obj->wunits);
	}

	/**
	 * Function to get the tag of a product
	 * 
	 * @param 	int 	$idprod		Id of a product
	 * 
	 * @return 	String				Most precise tag
	 */
	public function getTag($idprod){
		$sql = "SELECT tagp.fk_categorie as tag";
		$sql.= " FROM ".MAIN_DB_PREFIX."categorie_product as tagp";
		$sql.= " LEFT JOIN ".MAIN_DB_PREFIX."categorie as tagc on tagc.rowid = tagp.fk_categorie";
		$sql.= " WHERE tagc.fk_parent = 0 AND tagc.rowid != 140 AND tagp.fk_product = ".$idprod;
		$result = $this->db->query($sql);
		if ($result){
			$obj = $this->db->fetch_object($result);
		} else {
			$this->db->error();
		}

		return $obj->tag;
	}
}