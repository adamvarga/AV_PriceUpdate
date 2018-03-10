<?php

class AV_PriceUpdate_Model_Update
{
    /*
     * Get resource
     */

    public function getResource($product)
    {
        return $product->getResource();
    }

    /*
     * Get product collection
     */

    public function getProductCollection()
    {
        return Mage::getResourceModel('catalog/product_collection')->addAttributeToSelect('*');
    }

    /*
     * Load percent value from config
     */

    public function getPercent()
    {
        $percent = Mage::getStoreConfig('av_priceupdate/general/increase');
        if (!$percent || !preg_match('/\\d/', $percent)) {
            return false;
        } else {
            $format_percent = preg_replace('/[^0-9-]/', '', $percent);
            return $this->percentToDecimal($format_percent);
        }
    }

    /*
     * Get CSV Data to custom price update
     */

    public function getCsv()
    {
        $file_name = Mage::getStoreConfig('av_priceupdate/general/file');
        $file = Mage::getBaseDir() . DS . 'var/priceupdate' . DS . $file_name;
        $ext = pathinfo($file, PATHINFO_EXTENSION);
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mtype = finfo_file($finfo, $file);
        finfo_close($finfo);

        if (strtolower($ext) == 'csv' && in_array($mtype, array('text/csv', 'text/anytext', 'text/plain', 'text/comma-separated-values', 'application/csv', 'application/excel', 'application/vnd.ms-excel', 'application/vnd.msexcel'))) {
            return $this->readDataFromCsv($file);
        } else {
            Mage::log('File: ' . $file . ' - wrong format error - ', Zend_Log::ERR, 'exception.log', true);
        }
    }

    /*
     * Read data from csv
     */

    public function readDataFromCsv($file, $column_name = false)
    {
        if ($file) {
            $csv = new Varien_File_Csv();
            $csv_data = $csv->getData($file);
            if ($column_name) {
                $columns = array_shift($csv_data);
                foreach ($csv_data as $k => $v) {
                    $csv_data[$k] = array_combine($columns, array_values($v));
                }
            }
            return $this->setPriceFromCsv($csv_data);
        } else {
            Mage::log('File: ' . $file . ' - unable to read csv file - ', Zend_Log::ERR, 'exception.log', true);
        }

    }

    /*
     * Set product price from csv
     */

    public function setPriceFromCsv($csv_data)
    {
        foreach ($csv_data as $lines => $line) {
            if ($lines == 0) {
                continue;
            }

            $sku = $line[0];
            $price = $line[1];
            $special_price = $line[2];
            $format_price = preg_replace("/[^0-9,.]/", "", $price);
            $format_special_price = preg_replace("/[^0-9,.]/", "", $special_price);
            $product_id = Mage::getModel("catalog/product")->getIdBySku($sku);
            $update_resource = Mage::getResourceSingleton('catalog/product_action');

            if ($product_id) {
                if ($format_price || $format_special_price) {
                    $update_resource->updateAttributes(
                        array($product_id),
                        array(
                            'price' => $format_price,
                            'special_price' => $format_special_price
                        ), 0);
                }
            } else {
                Mage::log('Product - ' . $sku . ' not found', Zend_Log::ERR, 'exception.log', true);
            }
        }
        $this->runFinishReport();
    }

    /*
     * Convert percent value to decimal
     */

    public function percentToDecimal($pct)
    {
        $dec = $pct / 100;

        return $dec;
    }

    /*
     * Get product resource to change the regular price
     */

    public function changeRegularPrice($product, $percent)
    {
        if ($product && $percent) {
            $resource = $this->getResource($product);
            $product->setData('price', $percent);
            return $resource->saveAttribute($product, 'price');
        }
    }

    /*
     * Get product resource to change the special price
     */

    public function changeSpecialPrice($product, $percent)
    {
        if ($product && $percent) {
            $resource = $this->getResource($product);
            $product->setData('special_price', $percent);
            return $resource->saveAttribute($product, 'special_price');
        }
    }

    /*
    *  Add finish report
    */

    public function runFinishReport()
    {
        $this->clearIndex();
        $this->clearCache();
        $this->sendMail();
    }

    /*
     * Load product collection to change the price
     */

    public function getProducts()
    {
        $products = $this->getProductCollection();

        if ($this->getPercent()) {
            $increase_value = $this->getPercent();
        } else {
            return $this->getCsv();
        }

        if (!$increase_value) {
            $msg_error = Mage::helper('av_priceupdate')->__('Please fill the field correctly "Percent price increase"!');
            return $this->sendMail($msg_error);
        } elseif (Mage::getStoreConfig('av_priceupdate/general/file')) {
            $msg_error = Mage::helper('av_priceupdate')->__('Please remove the csv file, if you want update the prices only with percent value!');
            return $this->sendMail($msg_error);
        }

        foreach ($products as $product) {
            $product_price = (float)$product->getPrice();
            $product_special_price = (float)$product->getSpecialPrice();

            if (strpos($increase_value, '-') !== false) {
                if ($product_special_price) {
                    $new_price_minus = $product_special_price + ($product_special_price * $increase_value);
                    $this->changeSpecialPrice($product, $new_price_minus);
                } else {
                    $new_price_minus = $product_price + ($product_price * $increase_value);
                    $this->changeRegularPrice($product, $new_price_minus);
                }
            } else {
                if ($product_special_price) {
                    $new_price_plus = $product_special_price + ($product_special_price * $increase_value);
                    $this->changeSpecialPrice($product, $new_price_plus);
                } else {
                    $new_price_plus = $product_price + ($product_price * $increase_value);
                    $this->changeRegularPrice($product, $new_price_plus);
                }
            }
        }
        $this->runFinishReport();
    }

    /*
     * Send email notification
     */

    public function sendMail($msg_error)
    {
        $template_id = 'priceupdate';
        $mail = Mage::getModel('core/email_template')->loadDefault($template_id);
        $mail_from = Mage::getStoreConfig('trans_email/ident_general/email');
        $mail_to = Mage::getStoreConfig('trans_email/ident_custom1/email');
        $customer_name = Mage::getStoreConfig('trans_email/ident_general/name');
        $mail_subject = "AV PriceUpdate Report";
        $mail_name = Mage::getStoreConfig('trans_email/ident_general/name');
        $mail->setSenderName($mail_name);
        $mail->setSenderEmail($mail_to);

        if (!$msg_error) {
            $msg = Mage::helper('av_priceupdate')->__('Product prices have been updated.');
        } else {
            $msg = $msg_error;
        }

        $email_template_variables = array(
            'customer_name' => $customer_name,
            'message' => $msg
        );

        $mail->setTemplateSubject(trim($mail_subject));
        $mail->setFromEmail($mail_from);
        $mail->setFromName($mail_name);
        $mail->setType('html');

        try {
            $mail->send($mail_to, $customer_name, $email_template_variables);
        } catch (Exception $e) {
            Mage::log($e, 'exception.log', true);

        }
    }

    /*
    * Clear magento indexes
    */

    public function clearIndex()
    {
        $indexingProcesses = Mage::getSingleton('index/indexer')->getProcessesCollection();
        foreach ($indexingProcesses as $process) {
            $process->reindexEverything();
        }
    }

    /*
     * Clear magento cache
     */

    public function clearCache()
    {
        $allTypes = Mage::app()->useCache();
        foreach ($allTypes as $type => $cache) {
            Mage::app()->getCacheInstance()->cleanType($type);
        }
    }
}