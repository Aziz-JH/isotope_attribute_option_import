<?php

namespace IsotopeAttributeOptionImport;

use Contao\Backend;
use Contao\Controller;
use Contao\File;
use Contao\Input;
use Contao\Message;
use Contao\System;
use Isotope\Model\AttributeOption;

class Attribute extends Backend {

	public $importTypes = array('json');


	public function getUploads(&$objUploader) {
		//add config b  uploadTypes and add
		$arrUploadTypes = explode(',', $GLOBALS['TL_CONFIG']['uploadTypes']);
		$uploadTypesCache = $GLOBALS['TL_CONFIG']['uploadTypes'];
		$GLOBALS['TL_CONFIG']['uploadTypes'] = implode(',',array_merge($this->importTypes, $arrUploadTypes));



		$arrUploaded = $objUploader->uploadTo('system/tmp');
		$GLOBALS['TL_CONFIG']['uploadTypes'] = $uploadTypesCache;

		return $arrUploaded;
	}


	/**
	 * @param $arrData
	 * @param $id
	 * @return bool
	 */
	public function saveAttributeOptions($arrData, $id) {
		$arrAttributeOptions = array();
		$time = time();
		$ptable = \Isotope\Model\Attribute::getTable();

		foreach($arrData as $group => $groupData) {
			if(is_array($groupData)) {
				$arrAttributeOptions[] = array(
					'pid' => $id,
					'sorting' => 0,
					'tstamp' => $time,
					'ptable' => $ptable,
					'type' => 'group',
					'label' => $group,
					'published' => 1
				);

				foreach($groupData as $optionData) {
					if(is_array($optionData)) {
						//@todo if there are more options
					} else {
						$arrAttributeOptions[] = array(
							'pid' => $id,
							'sorting' => 0,
							'tstamp' => $time,
							'ptable' => $ptable,
							'type' => 'option',
							'label' => $optionData,
							'published' => 1
						);
					}
				}

			} else {
				$arrAttributeOptions[] = array(
					'pid' => $id,
					'sorting' => 0,
					'tstamp' => $time,
					'ptable' => $ptable,
					'type' => 'option',
					'label' => $groupData,
					'published' => 1
				);
			}
		}

		$sorting = 0;
		foreach ($arrAttributeOptions as $arrData) {
			$objAttributeOption = new AttributeOption();
			$objAttributeOption->setRow($arrData);
			$objAttributeOption->sorting = $sorting;
			$objAttributeOption->save();
			$sorting += 128;
		}

		return true;
	}

	/**
	 * @return string
	 */
	public function generate() {
		if (Input::get('key') != 'import' || !isset($_GET['id'])) {
			return '';
		}

		$this->import('BackendUser', 'User');
		$class = $this->User->uploader;
		// See #4086 and #7046
		if (!class_exists($class) || $class == 'DropZone') {
			$class = 'FileUpload';
		}
		/** @var \FileUpload $objUploader */
		$objUploader = new $class();
		// Import CSS
		if (Input::post('FORM_SUBMIT') == 'tl_iso_attribute_option_import') {

			$arrUploaded = $this->getUploads($objUploader);
			if (empty($arrUploaded)) {
				Message::addError($GLOBALS['TL_LANG']['ERR']['all_fields']);
				Controller::reload();
			}

			foreach ($arrUploaded as $strFile) {
				if (is_dir(TL_ROOT . '/' . $strFile)) {
					Message::addError(sprintf($GLOBALS['TL_LANG']['ERR']['importFolder'], basename($strFile)));
					continue;
				}
				$objFile = new \File($strFile, true);

				$strContent = $objFile->getContent();
				switch($objFile->extension) {
					case('json'):
						$arrContent = json_decode($strContent, true);
						break;
					default:
						Message::addError(sprintf($GLOBALS['TL_LANG']['ERR']['filetype'], $objFile->extension));
						continue;
						break;
				}

				if(!isset($arrContent) || !is_array($arrContent)) {
					\Message::addError('is no array');
					Controller::reload();
					return false;
				}

				if($this->saveAttributeOptions($arrContent, Input::get('id'))) {
					// Redirect
					//\System::setCookie('BE_PAGE_OFFSET', 0, 0);
					//Controller::redirect(str_replace('&key=import', '', \Environment::get('request')));
				};
			}
		}

// Return form
		return '
<div id="tl_buttons">
<a href="' .ampersand(str_replace('&key=import', '', \Environment::get('request'))). '" class="header_back" title="' .specialchars($GLOBALS['TL_LANG']['MSC']['backBTTitle']). '" accesskey="b">' .$GLOBALS['TL_LANG']['MSC']['backBT']. '</a>
</div>
' .Message::generate(). '
<form action="' .ampersand(\Environment::get('request'), true). '" id="tl_iso_attribute_option_import" class="tl_form" method="post" enctype="multipart/form-data">
<div class="tl_formbody_edit">
<input type="hidden" name="FORM_SUBMIT" value="tl_iso_attribute_option_import">
<input type="hidden" name="REQUEST_TOKEN" value="'.REQUEST_TOKEN.'">
<input type="hidden" name="MAX_FILE_SIZE" value="'.\Config::get('maxFileSize').'">
<div class="tl_tbox">
  <h3>Import attribute options</h3>'.$objUploader->generateMarkup().(isset($GLOBALS['TL_LANG']['tl_style_sheet']['source'][1]) ? '
  <p class="tl_help tl_tip">234</p>' : '').'
</div>
</div>
<div class="tl_formbody_submit">
<div class="tl_submit_container">
  <input type="submit" name="save" id="save" class="tl_submit" accesskey="s" value="import">
</div>
</div>
</form>';
	}
	/**
	 * Import files from selected folder
	 *
	 * @param string $strPath
	 */
	protected function importFromPath($strPath)
	{
		$arrFiles = scan(TL_ROOT . '/' . $strPath);
		if (empty($arrFiles)) {
			Message::addError($GLOBALS['TL_LANG']['MSC']['noFilesInFolder']);
			Controller::reload();
		}
		$blnEmpty    = true;
		$arrDelete   = array();
		$objProducts = \Database::getInstance()->prepare("SELECT * FROM tl_iso_product WHERE pid=0")->execute();
		while ($objProducts->next()) {
			$arrImageNames = array();
			$arrImages     = deserialize($objProducts->images);
			if (!is_array($arrImages)) {
				$arrImages = array();
			} else {
				foreach ($arrImages as $row) {
					if ($row['src']) {
						$arrImageNames[] = $row['src'];
					}
				}
			}
			$arrPattern   = array();
			$arrPattern[] = $objProducts->alias ? standardize($objProducts->alias) : null;
			$arrPattern[] = $objProducts->sku ? $objProducts->sku : null;
			$arrPattern[] = $objProducts->sku ? standardize($objProducts->sku) : null;
			$arrPattern[] = !empty($arrImageNames) ? implode('|', $arrImageNames) : null;
			// !HOOK: add custom import regex patterns
			if (isset($GLOBALS['ISO_HOOKS']['addAssetImportRegexp']) && is_array($GLOBALS['ISO_HOOKS']['addAssetImportRegexp'])) {
				foreach ($GLOBALS['ISO_HOOKS']['addAssetImportRegexp'] as $callback) {
					$objCallback = System::importStatic($callback[0]);
					$arrPattern  = $objCallback->$callback[1]($arrPattern, $objProducts);
				}
			}
			$strPattern = '@^(' . implode('|', array_filter($arrPattern)) . ')@i';
			$arrMatches = preg_grep($strPattern, $arrFiles);
			if (!empty($arrMatches)) {
				$arrNewImages = array();
				foreach ($arrMatches as $file) {
					if (is_dir(TL_ROOT . '/' . $strPath . '/' . $file)) {
						$arrSubfiles = scan(TL_ROOT . '/' . $strPath . '/' . $file);
						if (!empty($arrSubfiles)) {
							foreach ($arrSubfiles as $subfile) {
								if (is_file($strPath . '/' . $file . '/' . $subfile)) {
									$objFile = new File($strPath . '/' . $file . '/' . $subfile);
									if ($objFile->isGdImage) {
										$arrNewImages[] = $strPath . '/' . $file . '/' . $subfile;
									}
								}
							}
						}
					} elseif (is_file(TL_ROOT . '/' . $strPath . '/' . $file)) {
						$objFile = new \File($strPath . '/' . $file);
						if ($objFile->isGdImage) {
							$arrNewImages[] = $strPath . '/' . $file;
						}
					}
				}
				if (!empty($arrNewImages)) {
					foreach ($arrNewImages as $strFile) {
						$pathinfo = pathinfo(TL_ROOT . '/' . $strFile);
						// Will recursively create the folder
						$objFolder = new \Folder('isotope/' . strtolower(substr($pathinfo['filename'], 0, 1)));
						$strCacheName = $pathinfo['filename'] . '-' . substr(md5_file(TL_ROOT . '/' . $strFile), 0, 8) . '.' . $pathinfo['extension'];
						\Files::getInstance()->copy($strFile, $objFolder->path . '/' . $strCacheName);
						$arrImages[] = array('src' => $strCacheName);
						$arrDelete[] = $strFile;
						Message::addConfirmation(sprintf($GLOBALS['TL_LANG']['MSC']['assetImportConfirmation'], $pathinfo['filename'] . '.' . $pathinfo['extension'], $objProducts->name));
						$blnEmpty = false;
					}
					\Database::getInstance()->prepare("UPDATE tl_iso_product SET images=? WHERE id=?")->execute(serialize($arrImages), $objProducts->id);
				}
			}
		}
		if (!empty($arrDelete)) {
			$arrDelete = array_unique($arrDelete);
			foreach ($arrDelete as $file) {
				\Files::getInstance()->delete($file);
			}
		}
		if ($blnEmpty) {
			\Message::addInfo($GLOBALS['TL_LANG']['MSC']['assetImportNoFilesFound']);
		}
		\Controller::reload();
	}
}
class asd extends \PHPUnit_Framework_TestCase {
	public function setUp() {

	}
}