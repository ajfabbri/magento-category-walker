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

// For more info, see:
// app/code/core/Mage/Catalog/Model/Category.php 
// Online docs here:
// http://docs.magentocommerce.com/Mage_Catalog/Mage_Catalog_Model_Category.html

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
 */
function product_categories_tostring($pid, $prod)
{
	$sku = $prod->getSku();
	$name = $prod->getName();

	$cat_ids = $prod->getCategoryIds();
	$output = "Product $pid, $sku, $name: ";
	$category = Mage::getModel('catalog/category');
	foreach ($cat_ids as $cid) {
		$cat_i = $category->load($cid);
		$output .= category_to_name_path($cat_i) . ",  ";
	}
	return $output;
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
	if (count($path_to_root) != 4) {
		print "XXX SKU $sku is not bottom level categorized.\n";
	}
	foreach ($path_to_root as $path_cid) {
		$cat_i = Mage::getModel('catalog/category')->load($path_cid);
		print "[$path_cid," . $cat_i->getName() . "]->";
	}
	print "\n";
}

/**
 * Return depth of category, where Root category is 0.
 * @param $category instance of Mage_Catalog_Model_Category
 */
function get_category_depth($category) {
	return $category->getLevel();
}

/**
 * Given a category, return a string of the path to the root, using names
 * instead of Id numbers.
 * @param category Mage_Catalog_Model_Category instance
 */
function category_to_name_path($category) {
	$path_names = array();
	foreach ($category->getParentIds() as $node_id) {
		$node_cat = Mage::getModel('catalog/category')->load($node_id);
		$path_names[] = $node_cat->getName() . "($node_id)";
	}
	$path_names[] = $category->getName() . "(" . $category->getId() . ")";
	return implode($path_names, "/");
}

/**
 * @param $pid		product id
 * @param $prod		Mage_Catalog_Model_Product  product model
 * @param $fix_issues	When true, try to fix problems and return info.
 * @return Array of string descriptions of any issues found (when $fix_issues ==
 * 	False), or fixed.  When $fix_issues is False, an empty array return
 * 	means success.
 */
function verify_product_category($pid, $prod, $fix_issues=False) {
	$issues = array();

	$sku = $prod->getSku();
	$name = $prod->getName();
	$prod_desc = "sku: $sku, name: $name, pid: $pid";
	$has_issues = False;
	
	$category = Mage::getModel('catalog/category');

	$cat_ids = $prod->getCategoryIds();
	if (count($cat_ids) != 1) {
		$issues[] = "Not a single category. $prod_desc";
		$has_issues = True;
	}

	foreach ($cat_ids as $cat_id) {
		$category->load($cat_id);
		if (get_category_depth($category) != 4) {
			$issues[] = "  Not bottom-level category: $prod_desc:  " .
				category_to_name_path($category);
			$has_issues = true;
		}

		if (! $has_issues && $fix_issues) {
			// Ignore first two categories: Root Catalog(1),LIGHTSPEED_ROOT_CATEGORY(3)
			$new_cat_ids = array_slice($category->getParentIds(), 2); 
			$new_cat_ids[] = $category->getId(); 
			print "--- Fix: assign $pid to (" . implode($new_cat_ids, ",") . ").\n";
			$prod->setCategoryIds($new_cat_ids);
			$prod->save();
		}
	}
		
	//print "Path to root for $pid, $sku, $name: ";
	//$category = Mage::getModel('catalog/category');
	//$category->load($cat_id);

	return $issues;
}

/**
 * Verify that products are categorized as we expect.  We use LightSpeed PRO
 * point of sale, and it is supposed to only assign products to a bottom level
 * category.
 * @param $print_all  Print all products, followed by any errors.
 */
function check_product_categories($print_all) {
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

	$issues = array();
	foreach($product_ids as $pid) {

		// Not sure why we need a new model for every product load, or the
		// category data is broken.   I'm a Magento n00b.
		$p_i = Mage::getModel('catalog/product')->load($pid);
	
		if ($print_all) {
			print product_categories_tostring($pid, $p_i) . "\n";
		}

		$cat_issues = verify_product_category($pid, $p_i, True);
		$issues = array_merge($issues, $cat_issues);

		//fixup_product_categories($pid, $p_i, $existing_cat_ids[0]);

		// TODO
		//  remove root from list (?)
		//  if list of parents is not empty, assign to product

		//   $product->setCategoryIds(array($category->getId()));
		//    $product->save();
	}

	if (count($issues) > 0) {
		print "***** Found these errors: *****\n";
		foreach ($issues as $i) {
			print "$i\n";
		}
	} else {
		print "---- Test passed: All products belong to a single"
			. " bottom-level category ---\n";
	}
}


// Main

// For now, passing true will just print all products and their categories.
// Passing false will also cause it to warn about products that have number of
// categories assigned not equal to 1 (i.e. Lightspeed upload bug).
//read_products(false);
if ( basename(__FILE__) == basename($_SERVER["SCRIPT_FILENAME"]) ) {
	check_product_categories(true);
}

?>

