# MageGuide OverrideMediaStorage

Changes **catalog:images:resize** command to accept space-separated product ids  
Tested on Magento 2.2+

## Functionalities 

The magento *catalog:images:resize* command creates resized and cached product images.  
The problem is that when you execute it, it runs for every product, which may take literally many days if you have a big catalog.  

* With this module, the command accepts as a parameter a list of space-separated product ids, and will only run for these products.
* You can use it if you change the images of some products or if the image cache was manually cleared, and you want to recreate the cached resized images only for these products.

## Usage 

1. Copy the module files inside your app/code folder  
2. Enable the module with the following commands from your Magento root:  
```php bin/magento module:enable MageGuide_OverrideMediaStorage```  
```php bin/magento setup:upgrade```  
3. Execute the command with the products ids you need  
```php bin/magento catalog:images:resize 10001 10002```
