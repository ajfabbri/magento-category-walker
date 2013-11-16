<?php

//
// Command-line tool to enumerate all Magento products and the categoires
// assigned to them.
// Make sure the require_once command below can find the path to the Magento
// install, and just run as `php category-walker.php`.
//
// In the future, will also assign parent categories to items.
//
// Pretty trivial stuff, but let's make it GPLv2 to encourage sharing.
// @author Aaron Fabbri <ajfabbri@gmail.com>
// Copyright and License see LICENSE file in this directory.

define('SCRIPT_DIR', realpath(dirname(__FILE__)));
require_once SCRIPT_DIR . '/../../html/app/Mage.php';

// Mage::app() is static method to init or get magento app context
Mage::app()->setCurrentStore(Mage_Core_Model_App::ADMIN_STORE_ID);

$store = Mage::app()->getStore();
//print_r($store);

// Load products 

/**
 * @param $pid		integer product id
 * @param $prod		Mage_Catalog_Model_Product  product model
 * @param $cat_ids	array of category ids
 */
function print_product_categories($pid, $prod, $cat_ids)
{
	$sku = $prod->getSku();
	$name = $prod->getName();

	print "Product $pid, $sku, $name: ";
	$category = Mage::getModel('catalog/category');
	foreach ($cat_ids as $cid) {
		$cat_i = $category->load($cid);
		print "[$cid," . $cat_i->getName() . "],";
	}
	print "\n";
}

/**
 * @TODO Assign all parent categories to the product as well.
 * For now, this is read-only.
 * @param $pid		product id
 * @param $prod		Mage_Catalog_Model_Product  product model
 * @param $cat_id	category id that product belongs to.
 */
function fixup_product_categories($pid, $prod, $cat_id)
{
//	print "Fixup pid $pid cat_id $cat_id\n";
	$sku = $prod->getSku();
	$name = $prod->getName();

	print "Path to root for $pid, $sku, $name: ";
	$category = Mage::getModel('catalog/category');
	$category->load($cat_id);
	$path_to_root = $category->getParentIds();
	foreach ($path_to_root as $path_cid) {
		$cat_i = Mage::getModel('catalog/category')->load($path_cid);
		print "[$path_cid," . $cat_i->getName() . "]->";
	}
	print "\n";
}

/**
 * @param $just_print Don't change anything, just print out products.
 */
function read_products($just_print) {
	print "Loading products...\n";
	$prod = Mage::getModel('catalog/product');
	$products = $prod->getCollection();
	$product_ids = $products->getAllIds();

	$num_products = $products->count();

	print "done.\n";
	print "Got $num_products products.. Loading categories...\n";

	// Load categories
	$category = Mage::getModel('catalog/category');
	$categories = $category->getCollection();
	print "Got " . count($categories) . " categories.\n";


	// Not sure if we need a separate instance of the product model, or if all the
	// stuff we got above references separate copies.. -n00b
	$prod_m =  Mage::getModel('catalog/product');

	foreach($product_ids as $pid) {

		// Not sure why we need a new model for every product load, or the
		// category data is broken.   I'm a Magento n00b.
		$p_i = Mage::getModel('catalog/product')->load($pid);
		
		// get list of parent categories (path to root)
		$existing_cat_ids = $p_i->getCategoryIds();

		if ($just_print || count($existing_cat_ids) != 1) {
			if (!$just_print) {
				print "Not single category: ";
			}
			print_product_categories($pid, $p_i, $existing_cat_ids);
		} else {

			fixup_product_categories($pid, $p_i, $existing_cat_ids[0]);
		}
		// TODO
		//  remove root from list (?)
		//  if list of parents is not empty, assign to product

		//   $product->setCategoryIds(array($category->getId()));
		//    $product->save();
	}
}


// Main

// For now, passing true will just print all products and their categories.
// Passing false will also cause it to warn about products that have number of
// categories assigned not equal to 1 (i.e. Lightspeed upload bug).
//read_products(false);
read_products(true);

?>

