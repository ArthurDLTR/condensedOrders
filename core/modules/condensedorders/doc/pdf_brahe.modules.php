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
 *  \file       pdf_brahe.modules.php
 *  \ingroup    condensedorders
 *  \brief      PDF modele for CondensedOrders
 */

require_once DOL_DOCUMENT_ROOT.'/core/modules/commande/modules_commande.php';
require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/company.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/functions2.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/pdf.lib.php';

class pdf_brahe extends ModelePDFCommandes
{
    /**
     * @var DoliDB database of the dolibarr
     */
    public $db;
    /**
     * @var int Environment ID when using multicompany module
     */
    public $entity;
    /**
     * @var String model name
     */
    public $name;
    /**
     * @var String Model description
     */
    public $description;
    /**
     * @var int Save the name of generated file as the main doc when generating a doc with this template
     */
    public $update_main_doc_field;
    /**
     * @var String Document type
     */
    public $type;
    /**
     * @var String Dolibarr version of the loaded document
     */
    public $version = 'dolibarr';
    /**
     * @var Societe recipient
     */
    public $recipient;
    /**
     * Constructor
     * @param   DoliDB      $db     Database handler
     */
    public function __construct($db){
        global $langs, $mysoc;

        $this->db = $db;
        $this->name = 'brahe';
        $this->description = $langs->trans("BRAH_PDF_DESCR");
        $this->update_main_doc_field = 1; 

        // Dimension page
        $this->type = 'pdf';
        $formtarray = pdf_getFormat();

        $this->page_width = $formtarray['width'];
        $this->page_height = $formtarray['height'];
        $this->format = arrray($this->page_width, $this->page_height);
        $this->left_margin = getDolGlobalInt('MAIN_PDF_MARGIN_LEFT', 10);
        $this->right_margin = getDolGlobalInt('MAIN_PDF_MARGIN_RIGHT', 10);
        $this->top_margin = getDolGlobalInt('MAIN_PDF_MARGIN_TOP', 10);
        $this->bottom_margin = getDolGlobalInt('MAIN_PDF_MARGIN_BOTTOM', 10);

        $this->option_logo = 1;
        $this->option_tva = 0;
        $this->option_modereg = 0;
        $this->option_condreg = 0;
        $this->option_multilang = 0;
        $this->option_draft_watermark = 1;

        $this->emetteur = $mysoc;
        if(empty($this->emetteur->country_code)){
            $this->emetteur->country_code = substr($langs->defaultlang, -2);
        }

        // Define position of columns
        $this->posxdesc = $this->left_margin + 1;
        $this->tabTitleHeight = 5;
    }

    /**
	 *	Function to build pdf onto disk
	 *
	 *	@param		Expedition	$object			    Object shipping to generate (or id if old method)
	 *	@param		Translate	$outputlangs		Lang output object
	 *  @param		string		$srctemplatepath	Full path of source filename for generator using a template file
	 *  @param		int			$hidedetails		Do not show line details
	 *  @param		int			$hidedesc			Do not show desc
	 *  @param		int			$hideref			Do not show ref
	 *  @return     int         	    			1=OK, 0=KO
	 */
	public function write_file($object, $outputlangs, $srctemplatepath = '', $hidedetails = 0, $hidedesc = 0, $hideref = 0){
        global $user, $conf, $langs, $hookmanager;

		$object->fetch_thirdparty();

		if (!is_object($outputlangs)) {
			$outputlangs = $langs;
		}
		// For backward compatibility with FPDF, force output charset to ISO, because FPDF expect text to be encoded in ISO
		if (getDolGlobalString('MAIN_USE_FPDF')) {
			$outputlangs->charset_output = 'ISO-8859-1';
		}

		// Load traductions files required by page
		$outputlangs->loadLangs(array("main", "bills", "orders", "products", "dict", "companies", "other", "propal", "deliveries", "sendings", "productbatch"));

        global $outputlangsbis;
		$outputlangsbis = null;
		if (getDolGlobalString('PDF_USE_ALSO_LANGUAGE_CODE') && $outputlangs->defaultlang != getDolGlobalString('PDF_USE_ALSO_LANGUAGE_CODE')) {
			$outputlangsbis = new Translate('', $conf);
			$outputlangsbis->setDefaultLang(getDolGlobalString('PDF_USE_ALSO_LANGUAGE_CODE'));
			$outputlangsbis->loadLangs(array("main", "bills", "orders", "products", "dict", "companies", "propal", "deliveries", "sendings", "productbatch"));
		}

		$nblines = count($object->lines);

        // Loop on each lines to detect if there is at least one image to show
		$realpatharray = array();
		$this->atleastonephoto = false;
		if (getDolGlobalString('MAIN_GENERATE_SHIPMENT_WITH_PICTURE')) {
			$objphoto = new Product($this->db);

			for ($i = 0; $i < $nblines; $i++) {
				if (empty($object->lines[$i]->fk_product)) {
					continue;
				}

				$objphoto->fetch($object->lines[$i]->fk_product);

				if (getDolGlobalInt('PRODUCT_USE_OLD_PATH_FOR_PHOTO')) {
					$pdir = get_exdir($object->lines[$i]->fk_product, 2, 0, 0, $objphoto, 'product').$object->lines[$i]->fk_product."/photos/";
					$dir = $conf->product->dir_output.'/'.$pdir;
				} else {
					$pdir = get_exdir(0, 0, 0, 0, $objphoto, 'product');
					$dir = $conf->product->dir_output.'/'.$pdir;
				}

				$realpath = '';

				foreach ($objphoto->liste_photos($dir, 1) as $key => $obj) {
					if (!getDolGlobalInt('CAT_HIGH_QUALITY_IMAGES')) {
						// If CAT_HIGH_QUALITY_IMAGES not defined, we use thumb if defined and then original photo
						if ($obj['photo_vignette']) {
							$filename = $obj['photo_vignette'];
						} else {
							$filename = $obj['photo'];
						}
					} else {
						$filename = $obj['photo'];
					}

					$realpath = $dir.$filename;
					$this->atleastonephoto = true;
					break;
				}

				if ($realpath) {
					$realpatharray[$i] = $realpath;
				}
			}
		}
        if (count($realpatharray) == 0) {
			$this->posxpicture = $this->posxweightvol;
		}

        if ($conf->expedition->multidir_output[$conf->entity]) {
            $diroutputmassaction = $conf->expedition->multidir_output[$conf->entity].'/temp/massgeneration'.$user->id;

			// Definition of $dir and $file
            $filename = 'MULTI_BL';
            $nom = dol_now();
            $file = $diroutputmassaction.'/'.$filename.'_'.dol_print_date($nom, 'dayhourlog').'.pdf';

			if (!file_exists($diroutputmassaction)) {
				if (dol_mkdir($diroutputmassaction) < 0) {
					$this->error = $langs->transnoentities("ErrorCanNotCreateDir", $dir);
					return 0;
				}
			}
        }
    }
}