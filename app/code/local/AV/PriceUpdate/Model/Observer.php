<?php

class AV_PriceUpdate_Model_Observer extends Mage_Core_Model_Abstract {

    public function run() {
        $update = Mage::getModel('av_priceupdate/update');
        return $update->getProducts();
    }


}
