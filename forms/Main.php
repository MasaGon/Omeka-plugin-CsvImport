<?php
/**
 * @version $Id$
 * @copyright Center for History and New Media, 2008-2011
 * @license http://www.gnu.org/licenses/gpl-3.0.txt
 * @package CsvImport
 */

/**
 * The form on csv-import/index/index.
 *
 * @package CsvImport
 * @author CHNM
 * @copyright Center for History and New Media, 2008-2011
 */
class CsvImport_Form_Main extends Omeka_Form
{
    private $_columnDelimiter = ',';
    private $_fileDestinationDir;
    private $_maxFileSize;
    private $_requiredMimeTypes = array('text/csv', 'text/plain');
    private $_requiredExtensions = array('txt', 'csv');

    public function init()
    {
        parent::init();
        $this->setAttrib('id', 'csvimport');
        $this->setMethod('post'); 

        $this->_addFileElement();
        $values = get_db()->getTable('ItemType')->findPairsForSelectForm();
        array_unshift($values, 'Select Item Type');
        $this->addElement('select', 'item_type_id', array(
            'label' => 'Item Type',
            'multiOptions' => $values,
        ));
        $values = get_db()->getTable('Collection')->findPairsForSelectForm();
        array_unshift($values, 'Select Collection');
        $this->addElement('select', 'collection_id', array(
            'label' => 'Collection',
            'multiOptions' => $values,
        ));
        $this->addElement('checkbox', 'items_are_public', array(
            'label' => 'Items Are Public?',
        ));
        $this->addElement('checkbox', 'items_are_featured', array(
            'label' => 'Items Are Featured?',
        ));
        $this->addElement('text', 'column_delimiter', array(
            'label' => 'Column Delimiter',
            'value' => $this->_columnDelimiter,
            'required' => true,
            'size' => '1',
            'validators' => array(
                array('validator' => 'NotEmpty', 
                      'breakChainOnFailure' => true, 
                      'options' => array('messages' => array(
                            Zend_Validate_NotEmpty::IS_EMPTY => 
                                "Column delimiter must be one character long.",
                      )),
                ),
                array('validator' => 'StringLength', 'options' => array(
                    'min' => 1,
                    'max' => 1,
                    'messages' => array(
                        Zend_Validate_StringLength::TOO_SHORT => 
                            "Column delimiter must be one character long.",
                        Zend_Validate_StringLength::TOO_LONG => 
                            "Column delimiter must be one character long.",
                    ),
                )),
            ),
        ));
        $this->addElement('submit', 'submit', array(
            'label' => 'Next',
            'class' => 'submit submit-medium',
        ));
    }

    public function isValid($post)
    {
        // Too much POST data, return with an error.
        if (empty($post) && (int)$_SERVER['CONTENT_LENGTH'] > 0) {
            $maxSize = $this->getMaxFileSize()->toString();
            $this->csv_file->addError(
                "The file you have uploaded exceeds the maximum file size "
                . "allowed by the server. Please upload a file smaller "
                . "than $maxSize.");
            return false;
        }

        return parent::isValid($post);
    }

    private function _addFileElement()
    {
        $fileValidators = array(
            new Zend_Validate_File_Size(array(
                'max' => (string)$this->getMaxFileSize())),
            new Zend_Validate_File_Count(1),
        );
        if ($this->_requiredExtensions) {
            $fileValidators[] = 
                new Omeka_Validate_File_Extension($this->_requiredExtensions);
        }
        if ($this->_requiredMimeTypes) {
            $fileValidators[] = 
                new Omeka_Validate_File_MimeType($this->_requiredMimeTypes);
        }
        // Random filename in the temporary directory.
        // Prevents race condition.
        $filter = new Zend_Filter_File_Rename($this->_fileDestinationDir 
                    . '/' . md5(mt_rand() + microtime(true)));
        $this->addElement('file', 'csv_file', array(
            'label' => 'Upload Your CSV File',
            'required' => true,
            'validators' => $fileValidators,
            'destination' => $this->_fileDestinationDir,
        ));
        $this->csv_file->addFilter($filter);
    }

    public function setColumnDelimiter($delimiter)
    {
        $this->_columnDelimiter = $delimiter;
    }

    public function setFileDestination($dest)
    {
        $this->_fileDestinationDir = $dest;
    }

    public function setRequiredMimeType($mimeType)
    {
        if (empty($mimeType)) {
            $this->_requiredMimeTypes = array();
        } else {
            $mimeTypes = explode(',', $mimeType);
            $this->_requiredMimeTypes = $mimeTypes;
        }
    }

    public function setRequiredExtension($ext)
    {
        if (empty($ext)) {
            $this->_requiredExtensions = array();
        } else {
            $exts = explode(',', $ext);
            $this->_requiredExtensions = $exts;
        }
    }

    /**
     * Set the maximum size for an uploaded CSV file.
     *
     * If this is not set in the plugin configuration, 
     * defaults to 'upload_max_filesize' setting in php.
     * 
     * If this is set but it exceeds the aforementioned php setting, the size 
     * will be reduced to that lower setting. 
     */
    public function setMaxFileSize($size = null)
    {
        $phpIniSize = $this->_getSizeMeasure(ini_get('upload_max_filesize'));
	if ($size) {
	    $pluginIniSize = $this->_getSizeMeasure($size);
            if ($pluginIniSize->compare($phpIniSize)) {
                $this->_maxFileSize = $phpIniSize;
            } else {
                $this->_maxFileSize = $pluginIniSize;
            }
        } else {
            $this->_maxFileSize = $phpIniSize;
        }
    }

    public function getMaxFileSize()
    {
	if (!$this->_maxFileSize) {
	    $this->setMaxFileSize();
	}
        return $this->_maxFileSize;
    }

    private function _getSizeMeasure($size)
    {
        if (!preg_match('/(\d+)([BKMGT]?)/', $size, $matches)) {
            return false;
        }
        $sizeType = Zend_Measure_Binary::BYTE;
        // Why reimplement this?  Seems pointless, but no PHP API.
        $sizeTypes = array(
            'B' => Zend_Measure_Binary::BYTE,
            'K' => Zend_Measure_Binary::KILOBYTE,
            'M' => Zend_Measure_Binary::MEGABYTE,
            'G' => Zend_Measure_Binary::GIGABYTE,
            'T' => Zend_Measure_Binary::TERABYTE,
        );
        if (array_key_exists($matches[2], $sizeTypes)) {
            $sizeType = $sizeTypes[$matches[2]];
        }

        $measure = new Zend_Measure_Binary($matches[1], $sizeType);
        return $measure;
    }
}
