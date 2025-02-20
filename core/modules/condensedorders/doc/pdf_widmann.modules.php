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
        $formtarray = pdf_getFormat();

        $this->page_width = $formtarray['width'];
        $this->page_height = $formtarray['height'];
        $this->format = array($this->page_width, $this->page_height);
        $this->left_margin = getDolGlobalInt('MAIN_PDF_MARGIN_LEFT', 10);
        $this->right_margin = getDolGlobalInt('MAIN_PDF_MARGIN_RIGHT', 10);
        $this->top_margin = getDolGlobalInt('MAIN_PDF_MARGIN_TOP', 10);
        $this->bottom_margin = getDolGlobalInt('MAIN_PDF_MARGIN_BOTTOM', 10);
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

        if ($conf->expedition->multidir_output[$conf->entity]) {
            // Definition of $dir and $file
			if ($object->specimen) {
				$dir = $conf->expedition->dir_output."/sending/";
				$file = $dir."/SPECIMEN.pdf";
			} else {
				$expref = 'MULTI_BL_'.dol_print_date(dol_now(), 'dayhourlog');
				$dir = $conf->expedition->dir_output."/sending/temp/massgeneration/".$user->id;
				$file = $dir."/".$expref.".pdf";
			}

			if (!file_exists($dir)) {
				if (dol_mkdir($dir) < 0) {
					$this->error = $langs->transnoentities("ErrorCanNotCreateDir", $dir);
					return 0;
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
				$reshook = $hookmanager->executeHooks('beforePDFCreation', $parameters, $object, $action); // Note that $action and $object may have been modified by some hooks

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

                $pdf->SetTitle($outputlangs->convToOutputCharset('titre_test'));
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
                $top_shift = $this->_pagehead($pdf, $object, 1, $outputlangs);
				$pdf->SetFont('', '', $default_font_size - 1);
				$pdf->MultiCell(0, 3, ''); // Set interline to 3
				$pdf->SetTextColor(0, 0, 0);
                $tab_top = 90;	// position of top tab
				$tab_top_newpage = (!getDolGlobalInt('MAIN_PDF_DONOTREPEAT_HEAD') ? 42 + $top_shift : 10);
                
				$tab_height = $this->page_hauteur - $tab_top - $heightforfooter - $heightforfreetext;
                
				$this->posxdesc = $this->marge_gauche + 1;

                // Incoterm
				$height_incoterms = 0;
				if (isModEnabled('incoterm')) {
					$desc_incoterms = $object->getIncotermsForPDF();
					if ($desc_incoterms) {
						$tab_top -= 2;

						$pdf->SetFont('', '', $default_font_size - 1);
						$pdf->writeHTMLCell(190, 3, $this->posxdesc - 1, $tab_top - 1, dol_htmlentitiesbr($desc_incoterms), 0, 1);
						$nexY = $pdf->GetY();
						$height_incoterms = $nexY - $tab_top;

						// Rect takes a length in 3rd parameter
						$pdf->SetDrawColor(192, 192, 192);
						$pdf->Rect($this->marge_gauche, $tab_top - 1, $this->page_largeur - $this->marge_gauche - $this->marge_droite, $height_incoterms + 1);

						$tab_top = $nexY + 6;
						$height_incoterms += 4;
					}
				}

				// Public note and Tracking code
				$notetoshow = empty($object->note_public) ? '' : $object->note_public;

				// Extrafields in note
				$extranote = $this->getExtrafieldsInHtml($object, $outputlangs);
				if (!empty($extranote)) {
					$notetoshow = dol_concatdesc($notetoshow, $extranote);
				}

                // Section for notes 
                if (!empty($notetoshow) || !empty($object->tracking_number)) {
					$tab_top -= 2;
					$tab_topbeforetrackingnumber = $tab_top;

					// Tracking number
					if (!empty($object->tracking_number)) {
						$height_trackingnumber = 4;

						$pdf->SetFont('', 'B', $default_font_size - 2);
						$pdf->writeHTMLCell(60, $height_trackingnumber, $this->posxdesc - 1, $tab_top - 1, $outputlangs->transnoentities("TrackingNumber") . " : " . $object->tracking_number, 0, 1, false, true, 'L');
						$tab_top_alt = $pdf->GetY();

						$object->getUrlTrackingStatus($object->tracking_number);
						if (!empty($object->tracking_url)) {
							if ($object->shipping_method_id > 0) {
								// Get code using getLabelFromKey
								$code = $outputlangs->getLabelFromKey($this->db, $object->shipping_method_id, 'c_shipment_mode', 'rowid', 'code');
								$label = '';
								if ($object->tracking_url != $object->tracking_number) {
									$label .= $outputlangs->trans("LinkToTrackYourPackage")."<br>";
								}
								$label .= $outputlangs->trans("SendingMethod").": ".$outputlangs->trans("SendingMethod".strtoupper($code));
								//var_dump($object->tracking_url != $object->tracking_number);exit;
								if ($object->tracking_url != $object->tracking_number) {
									$label .= " : ";
									$label .= $object->tracking_url;
								}

								$height_trackingnumber += 4;
								$pdf->SetFont('', 'B', $default_font_size - 2);
								$pdf->writeHTMLCell(60, $height_trackingnumber, $this->posxdesc - 1, $tab_top_alt, $label, 0, 1, false, true, 'L');
							}
						}
						$tab_top = $pdf->GetY();
					}

					// Notes
					$pagenb = $pdf->getPage();
					if (!empty($notetoshow) || !empty($object->tracking_number)) {
						$tab_top -= 1;

						$tab_width = $this->page_largeur - $this->marge_gauche - $this->marge_droite;
						$pageposbeforenote = $pagenb;

						$substitutionarray = pdf_getSubstitutionArray($outputlangs, null, $object);
						complete_substitutions_array($substitutionarray, $outputlangs, $object);
						$notetoshow = make_substitutions($notetoshow, $substitutionarray, $outputlangs);
						$notetoshow = convertBackOfficeMediasLinksToPublicLinks($notetoshow);

						$pdf->startTransaction();

						$pdf->SetFont('', '', $default_font_size - 1);
						$pdf->writeHTMLCell(190, 3, $this->posxdesc - 1, $tab_top, dol_htmlentitiesbr($notetoshow), 0, 1);
						// Description
						$pageposafternote = $pdf->getPage();
						$posyafter = $pdf->GetY();

						if ($pageposafternote > $pageposbeforenote) {
							$pdf->rollbackTransaction(true);

							// prepare pages to receive notes
							while ($pagenb < $pageposafternote) {
								$pdf->AddPage();
								$pagenb++;
								if (!empty($tplidx)) {
									$pdf->useTemplate($tplidx);
								}
								if (!getDolGlobalInt('MAIN_PDF_DONOTREPEAT_HEAD')) {
									$this->_pagehead($pdf, $object, 0, $outputlangs);
								}
								// $this->_pagefoot($pdf,$object,$outputlangs,1);
								$pdf->setTopMargin($tab_top_newpage);
								// The only function to edit the bottom margin of current page to set it.
								$pdf->setPageOrientation('', 1, $heightforfooter + $heightforfreetext);
							}

							// back to start
							$pdf->setPage($pageposbeforenote);
							$pdf->setPageOrientation('', 1, $heightforfooter + $heightforfreetext);
							$pdf->SetFont('', '', $default_font_size - 1);
							$pdf->writeHTMLCell(190, 3, $this->posxdesc - 1, $tab_top, dol_htmlentitiesbr($notetoshow), 0, 1);
							$pageposafternote = $pdf->getPage();

							$posyafter = $pdf->GetY();

							if ($posyafter > ($this->page_hauteur - ($heightforfooter + $heightforfreetext + 20))) {	// There is no space left for total+free text
								$pdf->AddPage('', '', true);
								$pagenb++;
								$pageposafternote++;
								$pdf->setPage($pageposafternote);
								$pdf->setTopMargin($tab_top_newpage);
								// The only function to edit the bottom margin of current page to set it.
								$pdf->setPageOrientation('', 1, $heightforfooter + $heightforfreetext);
								//$posyafter = $tab_top_newpage;
							}


							// apply note frame to previous pages
							$i = $pageposbeforenote;
							while ($i < $pageposafternote) {
								$pdf->setPage($i);


								$pdf->SetDrawColor(128, 128, 128);
								// Draw note frame
								if ($i > $pageposbeforenote) {
									if (empty($height_trackingnumber)) {
										$height_note = $this->page_hauteur - ($tab_top_newpage + $heightforfooter);
									} else {
										$height_note = $this->page_hauteur - ($tab_top_newpage + $heightforfooter) + $height_trackingnumber + 1;
										$tab_top_newpage = $tab_topbeforetrackingnumber;
									}
									$pdf->Rect($this->marge_gauche, $tab_top_newpage - 1, $tab_width, $height_note + 2);
								} else {
									if (empty($height_trackingnumber)) {
										$height_note = $this->page_hauteur - ($tab_top + $heightforfooter);
									} else {
										$height_note = $this->page_hauteur - ($tab_top + $heightforfooter) + $height_trackingnumber + 1;
										$tab_top = $tab_topbeforetrackingnumber;
									}
									$pdf->Rect($this->marge_gauche, $tab_top - 1, $tab_width, $height_note + 2);
								}

								// Add footer
								$pdf->setPageOrientation('', 1, 0); // The only function to edit the bottom margin of current page to set it.
								$this->_pagefoot($pdf, $object, $outputlangs, 1);

								$i++;
							}

							// apply note frame to last page
							$pdf->setPage($pageposafternote);
							if (!empty($tplidx)) {
								$pdf->useTemplate($tplidx);
							}
							if (!getDolGlobalInt('MAIN_PDF_DONOTREPEAT_HEAD')) {
								$this->_pagehead($pdf, $object, 0, $outputlangs);
							}
							$height_note = $posyafter - $tab_top_newpage;
							$pdf->Rect($this->marge_gauche, $tab_top_newpage - 1, $tab_width, $height_note + 1);
						} else { // No pagebreak
							$pdf->commitTransaction();
							$posyafter = $pdf->GetY();
							if (empty($height_trackingnumber)) {
								$height_note = $posyafter - $tab_top + 1;
							} else {
								$height_note = $posyafter - $tab_top + $height_trackingnumber + 1;
								$tab_top = $tab_topbeforetrackingnumber;
							}
							$pdf->Rect($this->marge_gauche, $tab_top - 1, $tab_width, $height_note + 2);


							if ($posyafter > ($this->page_hauteur - ($heightforfooter + $heightforfreetext + 20))) {
								// not enough space, need to add page
								$pdf->AddPage('', '', true);
								$pagenb++;
								$pageposafternote++;
								$pdf->setPage($pageposafternote);
								if (!empty($tplidx)) {
									$pdf->useTemplate($tplidx);
								}
								if (!getDolGlobalInt('MAIN_PDF_DONOTREPEAT_HEAD')) {
									$this->_pagehead($pdf, $object, 0, $outputlangs);
								}

								$posyafter = $tab_top_newpage;
							}
						}

						$tab_height = $tab_height - $height_note;
						$tab_top = $posyafter + 6;
					} else {
						$height_note = 0;
					}
				}

                // Show barcode
				$height_barcode = 0;
				//$pdf->Rect($this->marge_gauche, $this->marge_haute, $this->page_largeur-$this->marge_gauche-$this->marge_droite, 30);
				if (isModEnabled('barcode') && getDolGlobalString('BARCODE_ON_SHIPPING_PDF')) {
					require_once DOL_DOCUMENT_ROOT.'/core/modules/barcode/doc/tcpdfbarcode.modules.php';

					$encoding = 'QRCODE';
					$module = new modTcpdfbarcode();
					$barcode_path = '';
					$result = 0;
					if ($module->encodingIsSupported($encoding)) {
						$result = $module->writeBarCode($object->ref, $encoding);

						// get path of qrcode image
						$newcode = $object->ref;
						if (!preg_match('/^\w+$/', $newcode) || dol_strlen($newcode) > 32) {
							$newcode = dol_hash($newcode, 'md5');
						}
						$barcode_path = $conf->barcode->dir_temp . '/barcode_' . $newcode . '_' . $encoding . '.png';
					}

					if ($result > 0) {
						$tab_top -= 2;

						$pdf->Image($barcode_path, $this->marge_gauche, $tab_top, 20, 20);

						$nexY = $pdf->GetY();
						$height_barcode = 20;

						$tab_top += 22;
					} else {
						$this->error = 'Failed to generate barcode';
					}
				}

                // Use new auto column system
				$this->prepareArrayColumnField($object, $outputlangs, $hidedetails, $hidedesc, $hideref);

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

                    $pdf->setTopMargin($tab_top_newpage);
					$pdf->setPageOrientation('', 1, $heightforfooter + $heightforfreetext + $heightforinfotot); // The only function to edit the bottom margin of current page to set it.
					$pageposbefore = $pdf->getPage();

					$showpricebeforepagebreak = 1;
					$posYAfterImage = 0;
					$posYAfterDescription = 0;
					$heightforsignature = 0;
                    var_dump($line);
                    print 'Ligne : '.$i.' ref prod : '.$line['ref'];
                    
                    $this->printStdColumnContent($pdf, $curY, 'prod_ref', $line['ref']);
                    $nexY = max($pdf->GetY(), $nexY);
					
                    $i.= 1;
                }


                
                // Pagefoot
				$this->_pagefoot($pdf, $object, $outputlangs);

                $pdf->Close();

				$pdf->Output($file, 'F');

                // Add pdfgeneration hook
				$hookmanager->initHooks(array('pdfgeneration'));
				$parameters = array('file' => $file, 'object' => $object, 'outputlangs' => $outputlangs);
				global $action;
				$reshook = $hookmanager->executeHooks('afterPDFCreation', $parameters, $this, $action); // Note that $action and $object may have been modified by some hooks
				if ($reshook < 0) {
					$this->error = $hookmanager->error;
					$this->errors = $hookmanager->errors;
				}

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
	 *	Show total to pay
	 *
	 *	@param	TCPDF		$pdf            Object PDF
	 *	@param  Expedition	$object         Object expedition
	 *	@param  int			$deja_regle     Amount already paid
	 *	@param	int         $posy           Start Position
	 *	@param	Translate	$outputlangs	Object langs
	 *	@return int							Position for suite
	 */
	protected function _tableau_tot(&$pdf, $object, $deja_regle, $posy, $outputlangs)
    {

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

    }

    /**
	 *  Show top header of page.
	 *
	 *  @param	TCPDF		$pdf     		Object PDF
	 *  @param  Expedition	$object     	Object to show
	 *  @param  int	    	$showaddress    0=no, 1=yes
	 *  @param  Translate	$outputlangs	Object lang for output
	 *  @return	float|int                   Return topshift value
	 */
	protected function _pagehead(&$pdf, $object, $showaddress, $outputlangs)
    {

    }

    /**
	 *   	Show footer of page. Need this->emetteur object
	 *
	 *   	@param	TCPDF		$pdf     			PDF
	 * 		@param	Expedition	$object				Object to show
	 *      @param	Translate	$outputlangs		Object lang for output
	 *      @param	int			$hidefreetext		1=Hide free text
	 *      @return	int								Return height of bottom margin including footer text
	 */
	protected function _pagefoot(&$pdf, $object, $outputlangs, $hidefreetext = 0)
    {

    }

    /**
	 *   	Define Array Column Field
	 *
	 *   	@param	Expedition	   $object    	    common object
	 *   	@param	Translate	   $outputlangs     langs
	 *      @param	int			   $hidedetails		Do not show line details
	 *      @param	int			   $hidedesc		Do not show desc
	 *      @param	int			   $hideref			Do not show ref
	 *      @return	void
	 */
	public function defineColumnField($object, $outputlangs, $hidedetails = 0, $hidedesc = 0, $hideref = 0)
    {

    }

}