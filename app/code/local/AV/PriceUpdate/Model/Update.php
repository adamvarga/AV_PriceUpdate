<?php

class AV_PriceUpdate_Model_Update
{
    /*
     * Load percent value from config
     */

    public function getPercent()
    {
        $percent = Mage::getStoreConfig('av_priceupdate/general/increase');
        if (!$percent || !preg_match('/\\d/', $percent)) {
           return false;
        } else {
            $format_percent = preg_replace('/[^0-9]/', '', $percent);
            return $this->percentToDecimal($format_percent);
        }
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
     * Load product collection to change the price
     */

    public function getProducts()
    {
        $products = Mage::getResourceModel('catalog/product_collection')
            ->addAttributeToSelect('price', 'id');
        $increase_value = $this->getPercent();
        if(!$increase_value) {
            $msg_error = Mage::helper('av_priceupdate')->__('Please fill the field correctly "Percent price increase"!');
            return $this->sendMail($msg_error);
        }

        foreach ($products as $product) {
            $product_price = (float)$product->getPrice();
            $new_price = $product_price + ($product_price * $increase_value);

            if ($product) {
                $resource = $product->getResource();
                $product->setData('price', $new_price);
                $resource->saveAttribute($product, 'price');
            }
        }
        $this->clearIndex();
        $this->clearCache();
        $this->sendMail();

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

    /*
     * Send email notification
     */

    public function sendMail($msg_error)
    {
        if (!$msg_error) {
            $msg = Mage::helper('av_priceupdate')->__('Product prices have been updated');
        } else {
            $msg = $msg_error;
        }
        $template_id = 'result';
        $mail = Mage::getModel('core/email_template')->loadDefault($template_id);
        $mail_from = Mage::getStoreConfig('trans_email/ident_general/email');
        $mail_to = Mage::getStoreConfig('trans_email/ident_custom1/email');
        $customer_name = Mage::getStoreConfig('trans_email/ident_general/name');
        $mail_subject = "AV PriceUpdate Report";
        $mail_name = Mage::getStoreConfig('trans_email/ident_general/name');
        $mail->setSenderName($mail_name);
        $mail->setSenderEmail($mail_to);
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
            Mage::getSingleton('core/session')->addSuccess('Your request has been sent');
        } catch (Exception $e) {
            Mage::getSingleton('core/session')->addError('Unable to send.');
        }
    }
}