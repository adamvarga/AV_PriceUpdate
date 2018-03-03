AV_PriceUpdate
=====================
- Custom price update with csv
- Global price update with percent increase / decrease
- Extra email notification / log
-------------------------------

Installation Instructions
-------------------------
1. Install the extension via GitHub, and deploy with modman.
2. Clear the cache, logout from the admin panel and then login again.
3. Set percent to global price increase / decrease in System -> Configuration -> AV_PriceUpdate -> Configuration -> Percent price increase. (av_priceupdate/general/increase) OR
4. Upload csv data to update the price by sku
4. Set the custom email correctly for notifications (trans_email/ident_custom1/email)

Example CSV:

![alt text](https://github.com/adamvarga/AV_PriceUpdate/blob/master/csv_example.png)

Uninstallation
--------------
1. Remove all extension files from your Magento installation OR
2. Modman remove AV_PriceUpdate & modman clean

ToDo
-------
Translate in de_DE

Support
-------
If you have any issues with this extension, open an issue on [GitHub](https://github.com/adamvarga).

Contribution
------------
Any contribution is highly appreciated. The best way to contribute code is to open a [pull request on GitHub](https://help.github.com/articles/using-pull-requests).

Developer
---------
Adam Varga
