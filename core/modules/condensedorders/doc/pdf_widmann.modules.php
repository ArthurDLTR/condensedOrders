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

require_once DOL_DOCUMENT_ROOT.'/core/modules/expedition/modules_expedition.php';
require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/company.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/functions2.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/functions.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/pdf.lib.php';

class pdf_widmann extends ModelePdfExpedition
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
        $this->name = 'widmann'; // Johannes Widmann, inventeur du + et du -
        $this->description = $langs->trans("WIDMANN_PDF_DESCR");
        $this->update_main_doc_field = 1; 
		
        // Dimension page
        $this->type = 'pdf';
        $formatarray = pdf_getFormat();
		
        $this->page_largeur = $formatarray['width'];
		$this->page_hauteur = $formatarray['height'];
		$this->format = array($this->page_largeur, $this->page_hauteur);
		$this->marge_gauche = getDolGlobalInt('MAIN_PDF_MARGIN_LEFT', 5);
		$this->marge_droite = getDolGlobalInt('MAIN_PDF_MARGIN_RIGHT', 5);
		$this->marge_haute = getDolGlobalInt('MAIN_PDF_MARGIN_TOP', 5); // Valeur à modifier pour changer la marge du haut de chaque page une fois que les problèmes d'affichage sur plusieurs pages seront réglés
		$this->marge_basse = getDolGlobalInt('MAIN_PDF_MARGIN_BOTTOM', 5);
    }
	
	/**
	 *	Function to build pdf onto disk
	 *
	 *	@param		CondensedOrders	$object			    Object shipping to generate (or id if old method)
	 *	@param		Translate		$outputlangs		Lang output object
	 *  @param		string			$srctemplatepath	Full path of source filename for generator using a template file
	 *  @param		int				$hidedetails		Do not show line details
	 *  @param		int				$hidedesc			Do not show desc
	 *  @param		int				$hideref			Do not show ref
	 *  @return     int         	    			1=OK, 0=KO
	 */
	public function write_file($object, $outputlangs, $srctemplatepath = '', $hidedetails = 0, $hidedesc = 0, $hideref = 0)
    {
		global $user, $conf, $langs, $hookmanager;
		
        $object->fetch_thirdparty();
		
		$print = array(
			'i' => "Indice : ",
			'nexY' => "nexY : ",
			'y' => "y : ",
			'pos' => "posAfterImage : ",
			'nb_page' => "Nombre de pages :",
			'pagepos' => "*position page : ",
			'fin' => "------------------------------------------- fin pour cet indice -----------------------------------------------",
		);
		
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

		// Loop on each lines to detect if there is at least one image to show
		$realpatharray = array();
		$this->atleastonephoto = false;
		if (getDolGlobalInt('CONDENSEDORDERS_WIDMANN_PICTURE')) {
			$objphoto = new Product($this->db);
			$i = 0;
			foreach ($object->lines as $key => $line) {
				if (empty($line['prod_id'])) {
					continue;
				}
				
				$objphoto->fetch($line['prod_id']);
				if (getDolGlobalInt('PRODUCT_USE_OLD_PATH_FOR_PHOTO')) {
					$pdir[0] = get_exdir($objphoto->id, 2, 0, 0, $objphoto, 'product').$objphoto->id."/photos/";
					$pdir[1] = get_exdir(0, 0, 0, 0, $objphoto, 'product').dol_sanitizeFileName($objphoto->ref).'/';
				} else {
					$pdir[0] = get_exdir(0, 0, 0, 0, $objphoto, 'product'); // default
					$pdir[1] = get_exdir($objphoto->id, 2, 0, 0, $objphoto, 'product').$objphoto->id."/photos/"; // alternative
				}
				
				$arephoto = false;
				foreach ($pdir as $midir) {
					if (!$arephoto) {
						if ($conf->entity != $objphoto->entity) {
							$dir = $conf->product->multidir_output[$objphoto->entity].'/'.$midir; //Check repertories of current entities
						} else {
							$dir = $conf->product->dir_output.'/'.$midir; //Check repertory of the current product
						}

						foreach ($objphoto->liste_photos($dir, 1) as $key => $obj) {
							if (!getDolGlobalInt('CAT_HIGH_QUALITY_IMAGES')) {		// If CAT_HIGH_QUALITY_IMAGES not defined, we use thumb if defined and then original photo
								if ($obj['photo_vignette']) {
									$filename = $obj['photo_vignette'];
								} else {
									$filename = $obj['photo'];
								}
							} else {
								$filename = $obj['photo'];
							}

							$realpath = $dir.$filename;
							$arephoto = true;
							$this->atleastonephoto = true;
						}
					}
				}
				
				if ($realpath && $arephoto) {
					$realpatharray[$i] = $realpath;
				}
				$i = $i + 1;
			}
		}

		switch($object->context){
			case 'orderlist':
				if($conf->commande->multidir_output[$conf->entity]){
					$diroutputmassaction = $conf->commande->multidir_output[$conf->entity]; //.'/temp/massgeneration/'.$user->id;
				}
				break;
			case 'shipmentlist':
				if($conf->expedition->multidir_output[$conf->entity]){
					$diroutputmassaction = $conf->expedition->multidir_output[$conf->entity]; //.'/sending/temp/massgeneration/'.$user->id;
				}
				break;
		}

        if ($diroutputmassaction) {
            // Definition of $dir and $file
			if ($object->specimen) {
				$dir = $conf->expedition->dir_output."/sending/";
				$file = $dir."/SPECIMEN.pdf";
			} else {
				switch($object->context){
					case 'orderlist':
						$now = dol_now();
						$dir = $diroutputmassaction."/temp/massgeneration/".$user->id;
						$filename = 'MULTI_BL';
						$file = $dir.'/'.$filename.'_'.dol_print_date($now, 'dayhourlog').'.pdf';
	
						if (!file_exists($diroutputmassaction)) {
							if (dol_mkdir($diroutputmassaction) < 0) {
								$this->error = $langs->transnoentities("ErrorCanNotCreateDir", $diroutputmassaction);
								return 0;
							}
						}
						break;
					case 'shipmentlist':
						$now = dol_now();
						$dir = $diroutputmassaction."/sending/temp/massgeneration/".$user->id;
						$expref = 'MULTI_BL_'.dol_print_date(dol_now(), 'dayhourlog');
						$file = $dir."/".$expref.".pdf";
						if (!file_exists($diroutputmassaction)) {
							if (dol_mkdir($diroutputmassaction) < 0) {
								$this->error = $langs->transnoentities("ErrorCanNotCreateDir", $diroutputmassaction);
								return 0;
							}
						}
						break;
				}
			}

            if (file_exists($dir)) {
				// Add pdfgeneration hook
				if (!is_object($hookmanager)) {
					include_once DOL_DOCUMENT_ROOT.'/core/class/hookmanager.class.php';
					$hookmanager = new HookManager($this->db);
				}
				$hookmanager->initHooks(array('pdfgeneration'));
				$parameters = array('file' => $file, 'object' => $object, 'outputlangs' => $outputlangs);
				global $action;
				// $reshook = $hookmanager->executeHooks('beforePDFCreation', $parameters, $object, $action); // Note that $action and $object may have been modified by some hooks

				// Set nblines with the new facture lines content after hook
				$nblines = is_array($object->lines) ? count($object->lines) : 0;

				$pdf = pdf_getInstance($this->format);
				$default_font_size = pdf_getPDFFontSize($outputlangs);
				$heightforinfotot = 8; // Height reserved to output the info and total part
				$heightforfreetext = (isset($conf->global->MAIN_PDF_FREETEXT_HEIGHT) ? $conf->global->MAIN_PDF_FREETEXT_HEIGHT : 5); // Height reserved to output the free text on last page
				$heightforfooter = $this->marge_basse + 8; // Height reserved to output the footer (value include bottom margin)
				if (getDolGlobalString('MAIN_GENERATE_DOCUMENTS_SHOW_FOOT_DETAILS')) {
					$heightforfooter += 6;
				}
				$pdf->SetAutoPageBreak(1, 0);

				if (class_exists('TCPDF')) {
					$pdf->setPrintHeader(false);
					$pdf->setPrintFooter(false);
				}
				$pdf->SetFont(pdf_getPDFFont($outputlangs));
				// Set path to the background PDF File
				if (!getDolGlobalString('MAIN_DISABLE_FPDI') && getDolGlobalString('MAIN_ADD_PDF_BACKGROUND')) {
					$pagecount = $pdf->setSourceFile($conf->mycompany->dir_output.'/' . getDolGlobalString('MAIN_ADD_PDF_BACKGROUND'));
					$tplidx = $pdf->importPage(1);
				}

				$pdf->Open();
				$pagenb = 0;
				$pdf->SetDrawColor(128, 128, 128);

                $pdf->SetTitle($outputlangs->convToOutputCharset('MultiBL'));
				$pdf->SetSubject($outputlangs->transnoentities("Shipment"));
				$pdf->SetCreator("Dolibarr ".DOL_VERSION);
                $pdf->SetAuthor($outputlangs->convToOutputCharset($user->getFullName($outputlangs)));
				$pdf->SetKeyWords($outputlangs->convToOutputCharset($object->ref)." ".$outputlangs->transnoentities("Shipment"));
				if (getDolGlobalString('MAIN_DISABLE_PDF_COMPRESSION')) {
					$pdf->SetCompression(false);
				}

                $pdf->SetMargins($this->marge_gauche, $this->marge_haute, $this->marge_droite); // Left, Top, Right

				// New page
				$pdf->AddPage();
				if (!empty($tplidx)) {
					$pdf->useTemplate($tplidx);
				}
				$pagenb++;
                $top_shift = 0; //$this->_pagehead($pdf, $object, 1, $outputlangs);
				$pdf->SetFont('', '', $default_font_size - 1);
				$pdf->MultiCell(0, 3, ''); // Set interline to 3
				$pdf->SetTextColor(0, 0, 0);
                $tab_top = $this->marge_haute; // 90 if pagehead active line 232	// position of top tab
				$tab_top_newpage = $this->marge_haute;
                
				$tab_height = $this->page_hauteur - $tab_top - $heightforfooter - $heightforfreetext;
                
				$nexY = $tap_top - 1;

				// print 'curX avant la prépa du tableau pour les colonnes machin : '.$this->page_largeur.' - '. $this->marge_droite;
                // Use new auto column system
				$this->prepareArrayColumnField($object, $outputlangs, $hidedetails, $hidedesc, $hideref);
				// var_dump($this->cols['ref']);
				// Table simulation to know the height of the title line
				$pdf->startTransaction();
				$this->pdfTabTitles($pdf, $tab_top, $tab_height, $outputlangs);
				$pdf->rollbackTransaction(true);

                $nexY = $tab_top + $this->tabTitleHeight;

                // Start of the writing in the file

                // Loop on each lines
				$pageposbeforeprintlines = $pdf->getPage();
				$pagenb = $pageposbeforeprintlines;
                $i = 0;
                foreach ($object->lines as $key => $line) {
                    $curY = $nexY;
					$pdf->SetFont('', '', $default_font_size - 1); // Into loop to work with multipage
					$pdf->SetTextColor(0, 0, 0);

                    $pdf->setTopMargin($this->marge_haute);
					$pdf->setPageOrientation('', 1, $heightforfooter + $heightforfreetext + $heightforinfotot); // The only function to edit the bottom margin of current page to set it.
					$pageposbefore = $pdf->getPage();

					$showpricebeforepagebreak = 0;
					$posYAfterImage = 0;
					$posYAfterDescription = 0;

					// Define size of image if we need it
					$imgsize = array();
					if (!empty($realpatharray[$i])) {
						$imgsize = pdf_getSizeForImage($realpatharray[$i]);
					}
					
					if (!empty($imgsize) && ($imgsize['width'] > 25 || $imgsize['height'] > 25)){
						$imglinesize = array(
							'width' => $imgsize['width'] / ($imgsize['width'] / 25),
							'height' => $imgsize['height'] / ($imgsize['height'] / 25),
						);
					} else {
						$imglinesize = array(
							'width' => $imgsize['width'],
							'height' => $imgsize['height'],
						);
					}
					
					
					if ($this->getColumnStatus('photo')) {
						// We start with Photo of product line
						if (isset($imglinesize['width']) && isset($imglinesize['height']) && ($curY + $imglinesize['height']) > ($this->page_hauteur)) {	// If photo too high, we moved completely on new page
							$pdf->AddPage('', '', true);
							if (!empty($tplidx)) {
								$pdf->useTemplate($tplidx);
							}
							//if (!getDolGlobalInt('MAIN_PDF_DONOTREPEAT_HEAD')) $this->_pagehead($pdf, $object, 0, $outputlangs);
							$pdf->setPage($pageposbefore + 1);

							$curY = $tab_top_newpage;

							// Allows data in the first page if description is long enough to break in multiples pages
							if (getDolGlobalString('MAIN_PDF_DATA_ON_FIRST_PAGE')) {
								$showpricebeforepagebreak = 1;
							} else {
								$showpricebeforepagebreak = 0;
							}
						}
						
						if (!empty($this->cols['photo']) && isset($imglinesize['width']) && isset($imglinesize['height']) && !empty($realpatharray[$i])) {
							$pdf->Image($realpatharray[$i], $this->getColumnContentXStart('photo'), $curY + 1, $imglinesize['width'], $imglinesize['height'], '', '', '', 2, 300); // Use 300 dpi
							// $pdf->Image does not increase value return by getY, so we save it manually
							$posYAfterImage = $curY + $imglinesize['height'] + 2;
						}
					}
					// Line changed compared to classic pdf model from Dolibarr to handle the page change
					$nexY = max(min($nexY, $pdf->getY()), $posYAfterImage);
					$pageposafter = $pdf->getPage();
					
					$pdf->setPage($pageposbefore);
					$pdf->setTopMargin($this->marge_haute);
					$pdf->setPageOrientation('', 1, 0); // The only function to edit the bottom margin of current page to set it.

					// We suppose that a too long description or photo were moved completely on next page
					if ($pageposafter > $pageposbefore) {
						$pdf->setPage($pageposafter);
						$curY = $tab_top_newpage;
					}
					
					$pdf->SetFont('', '', $default_font_size - 1); // We reposition the default font
					
                    // var_dump($line);
                    $prod = new Product($this->db);
					$prod->fetch($line['prod_id']);
					if ($this->getColumnStatus('ref')) {
						$this->printStdColumnContent($pdf, $curY, 'ref', $line['ref']. ' - ' . $prod->label);
						$nexY = max($pdf->GetY(), $nexY);
					}

					// Load the content of the details for the product concerned and display it in qte_det column
					$str_det = '';
					foreach($line['qte_det'] as $key => $det){
						$str_det.=$det['qte_expe'].' pour ';
						$soc = new Societe($this->db);
						$soc->fetch($det['soc']);
						if (!$det['ref_client']){
							$str_det.=$soc->name.'<br>';
						}else{
							$str_det.=$soc->name.' ('.$det['ref_client'].')<br>';
						}
					}
					
					if ($this->getColumnStatus('qte_det')) {
						$this->printStdColumnContent($pdf, $curY, 'qte_det', $str_det);
						$nexY = max($pdf->GetY(), $nexY);
					}
					
					if ($this->getColumnStatus('qte_tot')) {
						$this->printStdColumnContent($pdf, $curY, 'qte_tot', (int)$line['qte_tot']);
						$nexY = max($pdf->GetY(), $nexY);
					}
					
					// Add line
					if (getDolGlobalString('MAIN_PDF_DASH_BETWEEN_LINES') && $i < ($nblines - 1)) {
						$pdf->setPage($pageposafter);
						$pdf->SetLineStyle(array('dash' => '1,1', 'color' => array(80, 80, 80)));
						// $pdf->SetDrawColor(190,190,200);
						$pdf->line($this->marge_gauche, $nexY, $this->page_largeur - $this->marge_droite, $nexY);
						$pdf->SetLineStyle(array('dash' => 0));
					}					

					// Detect if some page were added automatically and output _tableau for past pages
					while ($pagenb < $pageposafter) {
						$pdf->setPage($pagenb);
						if ($pagenb == $pageposbeforeprintlines) {
							$this->_tableau($pdf, $tab_top, $this->page_hauteur - $tab_top - $heightforfooter, 0, $outputlangs, 0, 1);
						} else {
							$this->_tableau($pdf, $tab_top_newpage, $this->page_hauteur - $tab_top_newpage - $heightforfooter, 0, $outputlangs, 1, 1);
						}
						// $this->_pagefoot($pdf, $object, $outputlangs, 1);
						$pagenb++;
						$pdf->setPage($pagenb);
						$pdf->setPageOrientation('', 1, 0); // The only function to edit the bottom margin of current page to set it.
						// if (!getDolGlobalInt('MAIN_PDF_DONOTREPEAT_HEAD')) {
							// $this->_pagehead($pdf, $object, 0, $outputlangs);
						// }
						if (!empty($tplidx)) {
							$pdf->useTemplate($tplidx);
						}
					}
					if (isset($object->lines[$i + 1]->pagebreak) && $object->lines[$i + 1]->pagebreak) {
						if ($pagenb == $pageposafter) {
							$this->_tableau($pdf, $tab_top, $this->page_hauteur - $tab_top - $heightforfooter, 0, $outputlangs, $hidetop, 1, $object->multicurrency_code, $outputlangsbis);
						} else {
							$this->_tableau($pdf, $tab_top_newpage, $this->page_hauteur - $tab_top_newpage - $heightforfooter, 0, $outputlangs, 1, 1, $object->multicurrency_code, $outputlangsbis);
						}
						// $this->_pagefoot($pdf, $object, $outputlangs, 1);
						// New page
						$pdf->AddPage();
						if (!empty($tplidx)) {
							$pdf->useTemplate($tplidx);
						}
						$pagenb++;
					}

					// if($i > 8){
						// var_dump($print['i']);
						// var_dump($i);
						// var_dump($print['y']);
						// $y = $pdf->GetY();
						// var_dump($y);
						// var_dump($print['nb_page']);
						// var_dump($pagenb);
						// var_dump($print['pagepos']);
						// var_dump($pageposafter);
					// 	var_dump($print['fin']);
					// }

                    $i = $i + 1;
                }

				// Show square
				if ($pagenb == 1) {
					$this->_tableau($pdf, $tab_top, $this->page_hauteur - $tab_top - $heightforinfotot - $heightforfreetext - $heightforfooter, 0, $outputlangs, 0, 0);
					$bottomlasttab = $this->page_hauteur - $heightforinfotot - $heightforfreetext - $heightforfooter + 1;
				} else {
					$this->_tableau($pdf, $tab_top_newpage, $this->page_hauteur - $tab_top_newpage - $heightforinfotot - $heightforfreetext - $heightforfooter, 0, $outputlangs, 1, 0);
					$bottomlasttab = $this->page_hauteur - $heightforinfotot - $heightforfreetext - $heightforfooter + 1;
				}
                
                // Pagefoot
				// $this->_pagefoot($pdf, $object, $outputlangs);
				if (method_exists($pdf, 'AliasNbPages')) {
					$pdf->AliasNbPages();
				}

                $pdf->Close();

				$pdf->Output($file, 'F');

                // Add pdfgeneration hook
				// $hookmanager->initHooks(array('pdfgeneration'));
				// $parameters = array('file' => $file, 'object' => $object, 'outputlangs' => $outputlangs);
				// global $action;
				// $reshook = $hookmanager->executeHooks('afterPDFCreation', $parameters, $this, $action); // Note that $action and $object may have been modified by some hooks
				// if ($reshook < 0) {
				// 	$this->error = $hookmanager->error;
				// 	$this->errors = $hookmanager->errors;
				// }

                dolChmod($file);

				$this->result = array('fullpath' => $file);

				return 1; // No error
            } else {
                $this->error = $langs->transnoentities("ErrorCanNotCreateDir", $dir);
				return 0;
            }
        } else {
            $this->error = $langs->transnoentities("ErrorConstantNotDefined", "EXP_OUTPUTDIR");
			return 0;
        }
    }

    /**
	 *   Show table for lines
	 *
	 *   @param		TCPDF		$pdf     		Object PDF
	 *   @param		float|int	$tab_top		Top position of table
	 *   @param		float|int	$tab_height		Height of table (rectangle)
	 *   @param		int			$nexY			Y
	 *   @param		Translate	$outputlangs	Langs object
	 *   @param		int			$hidetop		Hide top bar of array
	 *   @param		int			$hidebottom		Hide bottom bar of array
	 *   @param		string		$currency		Currency code
	 *   @param		Translate	$outputlangsbis	Langs object bis
	 *   @return	void
	 */
	protected function _tableau(&$pdf, $tab_top, $tab_height, $nexY, $outputlangs, $hidetop = 0, $hidebottom = 0, $currency = '', $outputlangsbis = null)
    {
		global $conf;

		// Force to disable hidetop and hidebottom
		$hidebottom = 0;
		if ($hidetop) {
			$hidetop = -1;
		}

		$currency = !empty($currency) ? $currency : $conf->currency;
		$default_font_size = pdf_getPDFFontSize($outputlangs);

		// Amount in (at tab_top - 1)
		$pdf->SetTextColor(0, 0, 0);
		$pdf->SetFont('', '', $default_font_size - 2);

		if (empty($hidetop)) {
			if (getDolGlobalString('MAIN_PDF_TITLE_BACKGROUND_COLOR')) {
				$pdf->Rect($this->marge_gauche, $tab_top, $this->page_largeur - $this->marge_droite - $this->marge_gauche, $this->tabTitleHeight, 'F', null, explode(',', getDolGlobalString('MAIN_PDF_TITLE_BACKGROUND_COLOR')));
			}
		}

		$pdf->SetDrawColor(128, 128, 128);
		$pdf->SetFont('', '', $default_font_size - 1);

		// Output Rect
		$this->printRect($pdf, $this->marge_gauche, $tab_top, $this->page_largeur - $this->marge_gauche - $this->marge_droite, $tab_height, $hidetop, $hidebottom); // Rect takes a length in 3rd parameter and 4th parameter

		// var_dump($pdf);
		$this->pdfTabTitles($pdf, $tab_top, $tab_height, $outputlangs, $hidetop);

		if (empty($hidetop)) {
			$pdf->line($this->marge_gauche, $tab_top + $this->tabTitleHeight, $this->page_largeur - $this->marge_droite, $tab_top + $this->tabTitleHeight); // line takes a position y in 2nd parameter and 4th parameter
		}
    }

    /**
	 *  Show top header of page.
	 *
	 *  @param	TCPDF		$pdf     		Object PDF
	 *  @param  CondensedOrders	$object     	Object to show
	 *  @param  int	    	$showaddress    0=no, 1=yes
	 *  @param  Translate	$outputlangs	Object lang for output
	 *  @return	float|int                   Return topshift value
	 */
	protected function _pagehead(&$pdf, $object, $showaddress, $outputlangs)
    {
		global $conf, $langs, $mysoc;

		$langs->load("orders");

		$default_font_size = pdf_getPDFFontSize($outputlangs);

		pdf_pagehead($pdf, $outputlangs, $this->page_hauteur);

		//Prepare next
		$pdf->SetTextColor(0, 0, 60);
		$pdf->SetFont('', 'B', $default_font_size + 3);

		$w = 110;

		$posy = $this->marge_haute;
		$posx = $this->page_largeur - $this->marge_droite - $w;

		$pdf->SetXY($this->marge_gauche, $posy);

		// Logo
		if ($this->emetteur->logo) {
			$logodir = $conf->mycompany->dir_output;
			if (!empty($conf->mycompany->multidir_output[$object->entity])) {
				$logodir = $conf->mycompany->multidir_output[$object->entity];
			}
			if (!getDolGlobalInt('MAIN_PDF_USE_LARGE_LOGO')) {
				$logo = $logodir.'/logos/thumbs/'.$this->emetteur->logo_small;
			} else {
				$logo = $logodir.'/logos/'.$this->emetteur->logo;
			}
			if (is_readable($logo)) {
				$height = pdf_getHeightForLogo($logo);
				$pdf->Image($logo, $this->marge_gauche, $posy, 0, $height); // width=0 (auto)
			} else {
				$pdf->SetTextColor(200, 0, 0);
				$pdf->SetFont('', 'B', $default_font_size - 2);
				$pdf->MultiCell($w, 3, $outputlangs->transnoentities("ErrorLogoFileNotFound", $logo), 0, 'L');
				$pdf->MultiCell($w, 3, $outputlangs->transnoentities("ErrorGoToGlobalSetup"), 0, 'L');
			}
		} else {
			$text = $this->emetteur->name;
			$pdf->MultiCell($w, 4, $outputlangs->convToOutputCharset($text), 0, 'L');
		}

		$pdf->SetDrawColor(128, 128, 128);

		$posx = $this->page_largeur - $w - $this->marge_droite;
		$posy = $this->marge_haute;

		$pdf->SetFont('', 'B', $default_font_size + 2);
		$pdf->SetXY($posx, $posy);
		$pdf->SetTextColor(0, 0, 60);
		$title = $outputlangs->transnoentities("SendingSheet");
		$pdf->MultiCell($w, 4, $title, '', 'R');

		$pdf->SetFont('', '', $default_font_size + 1);

		$posy += 5;

		$pdf->SetXY($posx, $posy);
		$pdf->SetTextColor(0, 0, 60);
		$pdf->MultiCell($w, 4, $outputlangs->transnoentities("RefSending")." : ".$object->ref, '', 'R');

		// Date planned delivery
		if (!empty($object->date_delivery)) {
			$posy += 4;
			$pdf->SetXY($posx, $posy);
			$pdf->SetTextColor(0, 0, 60);
			$pdf->MultiCell($w, 4, $outputlangs->transnoentities("DateDeliveryPlanned")." : ".dol_print_date($object->date_delivery, "day", false, $outputlangs, true), '', 'R');
		}

		if (!getDolGlobalString('MAIN_PDF_HIDE_CUSTOMER_CODE') && !empty($object->thirdparty->code_client)) {
			$posy += 4;
			$pdf->SetXY($posx, $posy);
			$pdf->SetTextColor(0, 0, 60);
			$pdf->MultiCell($w, 3, $outputlangs->transnoentities("CustomerCode")." : ".$outputlangs->transnoentities($object->thirdparty->code_client), '', 'R');
		}


		$pdf->SetFont('', '', $default_font_size + 3);
		$Yoff = 25;

		// Add list of linked orders
		$origin = $object->origin;
		$origin_id = $object->origin_id;

		// $object->fetch_origin();

		// TODO move to external function
		if (isModEnabled($origin)) {     // commonly $origin='commande'
			$outputlangs->load('orders');

			$classname = ucfirst($origin);
			$linkedobject = new $classname($this->db);
			$result = $linkedobject->fetch($origin_id);
			if ($result >= 0) {
				//$linkedobject->fetchObjectLinked()   Get all linked object to the $linkedobject (commonly order) into $linkedobject->linkedObjects

				$pdf->SetFont('', '', $default_font_size - 2);
				$text = $linkedobject->ref;
				if (isset($linkedobject->ref_client) && !empty($linkedobject->ref_client)) {
					$text .= ' ('.$linkedobject->ref_client.')';
				}
				$Yoff = $Yoff + 8;
				$pdf->SetXY($this->page_largeur - $this->marge_droite - $w, $Yoff);
				$pdf->MultiCell($w, 2, $outputlangs->transnoentities("RefOrder")." : ".$outputlangs->transnoentities($text), 0, 'R');
				$Yoff = $Yoff + 3;
				$pdf->SetXY($this->page_largeur - $this->marge_droite - $w, $Yoff);
				$pdf->MultiCell($w, 2, $outputlangs->transnoentities("OrderDate")." : ".dol_print_date($linkedobject->date, "day", false, $outputlangs, true), 0, 'R');
			}
		}

		$top_shift = 0;
		// Show list of linked objects
		/*
		$current_y = $pdf->getY();
		$posy = pdf_writeLinkedObjects($pdf, $object, $outputlangs, $posx, $posy, $w, 3, 'R', $default_font_size);
		if ($current_y < $pdf->getY()) {
			$top_shift = $pdf->getY() - $current_y;
		}
		*/

		if ($showaddress) {
			// Sender properties
			$carac_emetteur = '';
			// Add internal contact of origin element if defined
			$arrayidcontact = array();
			if (!empty($origin) && is_object($object->origin_object)) {
				$arrayidcontact = $object->origin_object->getIdContact('internal', 'SALESREPFOLL');
			}
			if (is_array($arrayidcontact) && count($arrayidcontact) > 0) {
				$object->fetch_user(reset($arrayidcontact));
				$labelbeforecontactname = ($outputlangs->transnoentities("FromContactName") != 'FromContactName' ? $outputlangs->transnoentities("FromContactName") : $outputlangs->transnoentities("Name"));
				$carac_emetteur .= ($carac_emetteur ? "\n" : '').$labelbeforecontactname.": ".$outputlangs->convToOutputCharset($object->user->getFullName($outputlangs));
				$carac_emetteur .= (getDolGlobalInt('PDF_SHOW_PHONE_AFTER_USER_CONTACT') || getDolGlobalInt('PDF_SHOW_EMAIL_AFTER_USER_CONTACT')) ? ' (' : '';
				$carac_emetteur .= (getDolGlobalInt('PDF_SHOW_PHONE_AFTER_USER_CONTACT') && !empty($object->user->office_phone)) ? $object->user->office_phone : '';
				$carac_emetteur .= (getDolGlobalInt('PDF_SHOW_PHONE_AFTER_USER_CONTACT') && getDolGlobalInt('PDF_SHOW_EMAIL_AFTER_USER_CONTACT')) ? ', ' : '';
				$carac_emetteur .= (getDolGlobalInt('PDF_SHOW_EMAIL_AFTER_USER_CONTACT') && !empty($object->user->email)) ? $object->user->email : '';
				$carac_emetteur .= (getDolGlobalInt('PDF_SHOW_PHONE_AFTER_USER_CONTACT') || getDolGlobalInt('PDF_SHOW_EMAIL_AFTER_USER_CONTACT')) ? ')' : '';
				$carac_emetteur .= "\n";
			}

			$carac_emetteur .= pdf_build_address($outputlangs, $this->emetteur, $object->thirdparty, '', 0, 'source', $object);

			// Show sender
			$posy = getDolGlobalString('MAIN_PDF_USE_ISO_LOCATION') ? 40 : 42;
			$posx = $this->marge_gauche;
			if (getDolGlobalString('MAIN_INVERT_SENDER_RECIPIENT')) {
				$posx = $this->page_largeur - $this->marge_droite - 80;
			}

			$hautcadre = getDolGlobalString('MAIN_PDF_USE_ISO_LOCATION') ? 38 : 40;
			$widthrecbox = getDolGlobalString('MAIN_PDF_USE_ISO_LOCATION') ? 92 : 82;

			// Show sender frame
			if (!getDolGlobalString('MAIN_PDF_NO_SENDER_FRAME')) {
				$pdf->SetTextColor(0, 0, 0);
				$pdf->SetFont('', '', $default_font_size - 2);
				$pdf->SetXY($posx, $posy - 5);
				$pdf->MultiCell($widthrecbox, 5, $outputlangs->transnoentities("Sender"), 0, 'L');
				$pdf->SetXY($posx, $posy);
				$pdf->SetFillColor(230, 230, 230);
				$pdf->MultiCell($widthrecbox, $hautcadre, "", 0, 'R', 1);
				$pdf->SetTextColor(0, 0, 60);
				$pdf->SetFillColor(255, 255, 255);
			}

			// Show sender name
			if (!getDolGlobalString('MAIN_PDF_HIDE_SENDER_NAME')) {
				$pdf->SetXY($posx + 2, $posy + 3);
				$pdf->SetFont('', 'B', $default_font_size);
				$pdf->MultiCell($widthrecbox - 2, 4, $outputlangs->convToOutputCharset($this->emetteur->name), 0, 'L');
				$posy = $pdf->getY();
			}

			// Show sender information
			$pdf->SetXY($posx + 2, $posy);
			$pdf->SetFont('', '', $default_font_size - 1);
			$pdf->MultiCell($widthrecbox - 2, 4, $carac_emetteur, 0, 'L');


			// If SHIPPING contact defined, we use it
			$usecontact = false;
			$arrayidcontact = $object->origin_object->getIdContact('external', 'SHIPPING');
			if (count($arrayidcontact) > 0) {
				$usecontact = true;
				$result = $object->fetch_contact($arrayidcontact[0]);
			}

			// Recipient name
			if ($usecontact && ($object->contact->socid != $object->thirdparty->id && (!isset($conf->global->MAIN_USE_COMPANY_NAME_OF_CONTACT) || getDolGlobalString('MAIN_USE_COMPANY_NAME_OF_CONTACT')))) {
				$thirdparty = $object->contact;
			} else {
				$thirdparty = $object->thirdparty;
			}

			$carac_client_name = 'Test_client';

			$carac_client = pdf_build_address($outputlangs, $this->emetteur, $object->thirdparty, (!empty($object->contact) ? $object->contact : null), $usecontact, 'targetwithdetails', $object);

			// Show recipient
			$widthrecbox = getDolGlobalString('MAIN_PDF_USE_ISO_LOCATION') ? 92 : 100;
			if ($this->page_largeur < 210) {
				$widthrecbox = 84; // To work with US executive format
			}
			$posy = getDolGlobalString('MAIN_PDF_USE_ISO_LOCATION') ? 40 : 42;
			$posx = $this->page_largeur - $this->marge_droite - $widthrecbox;
			if (getDolGlobalString('MAIN_INVERT_SENDER_RECIPIENT')) {
				$posx = $this->marge_gauche;
			}

			// Show recipient frame
			if (!getDolGlobalString('MAIN_PDF_NO_RECIPENT_FRAME')) {
				$pdf->SetTextColor(0, 0, 0);
				$pdf->SetFont('', '', $default_font_size - 2);
				$pdf->SetXY($posx + 2, $posy - 5);
				$pdf->MultiCell($widthrecbox, 5, $outputlangs->transnoentities("Recipient"), 0, 'L');
				$pdf->Rect($posx, $posy, $widthrecbox, $hautcadre);
			}

			// Show recipient name
			$pdf->SetXY($posx + 2, $posy + 3);
			$pdf->SetFont('', 'B', $default_font_size);
			$pdf->MultiCell($widthrecbox, 2, $carac_client_name, 0, 'L');

			$posy = $pdf->getY();

			// Show recipient information
			$pdf->SetXY($posx + 2, $posy);
			$pdf->SetFont('', '', $default_font_size - 1);
			$pdf->MultiCell($widthrecbox, 4, $carac_client, 0, 'L');
		}

		$pdf->SetTextColor(0, 0, 0);

		return $top_shift;
    }

    /**
	 *   	Show footer of page. Need this->emetteur object
	 *
	 *   	@param	TCPDF		$pdf     			PDF
	 * 		@param	CondensedOrders	$object				Object to show
	 *      @param	Translate	$outputlangs		Object lang for output
	 *      @param	int			$hidefreetext		1=Hide free text
	 *      @return	int								Return height of bottom margin including footer text
	 */
	protected function _pagefoot(&$pdf, $object, $outputlangs, $hidefreetext = 0)
    {
		$showdetails = 0; // getDolGlobalInt('MAIN_GENERATE_DOCUMENTS_SHOW_FOOT_DETAILS', 0);
		return pdf_pagefoot($pdf, $outputlangs, 'SHIPPING_FREE_TEXT', $this->emetteur, $this->marge_basse, $this->marge_gauche, $this->page_hauteur, $object, $showdetails, $hidefreetext, $this->page_largeur, $this->watermark);
	}

    /**
	 *   	Define Array Column Field
	 *
	 *   	@param	CondensedOrders	   $object    	    common object
	 *   	@param	Translate	   $outputlangs     langs
	 *      @param	int			   $hidedetails		Do not show line details
	 *      @param	int			   $hidedesc		Do not show desc
	 *      @param	int			   $hideref			Do not show ref
	 *      @return	void
	 */
	public function defineColumnField($object, $outputlangs, $hidedetails = 0, $hidedesc = 0, $hideref = 0)
    {
		global $hookmanager;

		// Default field style for content
		$this->defaultContentsFieldsStyle = array(
			'align' => 'R', // R,C,L
			'padding' => array(1, 0.5, 1, 0.5), // Like css 0 => top , 1 => right, 2 => bottom, 3 => left
		);

		// Default field style for content
		$this->defaultTitlesFieldsStyle = array(
			'align' => 'C', // R,C,L
			'padding' => array(0.5, 0, 0.5, 0), // Like css 0 => top , 1 => right, 2 => bottom, 3 => left
		);

		/*
		 * For example
		 $this->cols['theColKey'] = array(
		 'rank' => $rank, // int : use for ordering columns
		 'width' => 20, // the column width in mm
		 'title' => array(
		 'textkey' => 'yourLangKey', // if there is no label, yourLangKey will be translated to replace label
		 'label' => ' ', // the final label : used fore final generated text
		 'align' => 'L', // text alignment :  R,C,L
		 'padding' => array(0.5,0.5,0.5,0.5), // Like css 0 => top , 1 => right, 2 => bottom, 3 => left
		 ),
		 'content' => array(
		 'align' => 'L', // text alignment :  R,C,L
		 'padding' => array(0.5,0.5,0.5,0.5), // Like css 0 => top , 1 => right, 2 => bottom, 3 => left
		 ),
		 );
		 */

		$rank = 0; // do not use negative rank
		
		// Product reference
		$this->cols['ref'] = array(
			'rank' => $rank,
			'width' => 56, // in mm
			'status' => true,
			'title' => array(
				'textkey' => 'Prod. ref'
			),
			'content' => array(
				'align' => 'L',
			),
			'border-left' => false, // add left line separator
		);
		if (getDolGlobalInt('CONDENSEDORDERS_WIDMANN_PICTURE')) {
			$rank = $rank + 1;
			$this->cols['photo'] = array(
				'rank' => $rank,
				'width' => 26,
				'status' => true,
				'title' => array(
					'textkey' => ''
				),
				'content' => array(
					'align' => 'C',
				),
				'border-left' => false,
			);

			$rank = $rank + 1;
			$this->cols['qte_det'] = array(
				'rank' => $rank,
				'width' => 110, // in mm
				'status' => true,
				'title' => array(
					'textkey' => 'Qté. détails'
				),
				'content' => array(
					'align' => 'L',
				),
				'border-left' => true, // remove left line separator
			);

			$rank = $rank + 1;
			$this->cols['qte_tot'] = array(
				'rank' => $rank,
				'width' => 10, // in mm
				'status' => true,
				'title' => array(
					'textkey' => 'Qté. tot'
				),
				'content' => array(
					'align' => 'C',
				),
				'border-left' => true, // remove left line separator
			);
		} else {
			$rank = $rank + 1;
			$this->cols['qte_det'] = array(
				'rank' => $rank,
				'width' => 111, // in mm
				'status' => true,
				'title' => array(
					'textkey' => 'Qté. détails'
				),
				'content' => array(
					'align' => 'L',
				),
				'border-left' => true, // remove left line separator
			);

			$rank = $rank + 1;
			$this->cols['qte_tot'] = array(
				'rank' => $rank,
				'width' => 25, // in mm
				'status' => true,
				'title' => array(
					'textkey' => 'Qté. tot'
				),
				'content' => array(
					'align' => 'C',
				),
				'border-left' => true, // add left line separator
			);
		}

		$parameters = array(
			'object' => $object,
			'outputlangs' => $outputlangs,
			'hidedetails' => $hidedetails,
			'hidedesc' => $hidedesc,
			'hideref' => $hideref
		);

		// $reshook = $hookmanager->executeHooks('defineColumnField', $parameters, $this); // Note that $object may have been modified by hook
		if ($reshook < 0) {
			setEventMessages($hookmanager->error, $hookmanager->errors, 'errors');
		} elseif (empty($reshook)) {
			// @phan-suppress-next-line PhanPluginSuspiciousParamOrderInternal
			$this->cols = array_replace($this->cols, $hookmanager->resArray); // array_replace is used to preserve keys
		} else {
			$this->cols = $hookmanager->resArray;
		}
    }

}