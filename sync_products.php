<!--  SYNC PRODUCTS FROM MAGENTO WEBSITE UPDATED IN LAST 24 hours -->

<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
ini_set('memory_limit', '5G');
ini_set('max_execution_time', '0');

error_reporting(E_ALL);

use Magento\Framework\App\Bootstrap;

require '/home/site/public_html/app/bootstrap.php';

$bootstrap = Bootstrap::create(BP, $_SERVER);

$objectManager = $bootstrap->getObjectManager();

$state = $objectManager->get('Magento\Framework\App\State');
$state->setAreaCode('frontend');

$userData = array("username" => "", "password" => ""); // Magento login 
$url = ""; // Magento site URL


$ch = curl_init("$url/index.php/rest/V1/integration/admin/token");
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($userData));
curl_setopt($ch, CURLOPT_TIMEOUT, 600);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, array("Content-Type: application/json", "Content-Length: " . strlen(json_encode($userData))));
 
$token = curl_exec($ch);

$localCategories = getLocalCategories();



$date = date("Y-m-d", strtotime('-24 hours', time()));

$ch = curl_init("$url/index.php/rest/V1/products/?searchCriteria[filter_groups][0][filters][0][field]=updated_at&searchCriteria[filter_groups][0][filters][0][value]=$date&searchCriteria[filter_groups][0][filters][0][condition_type]=gteq");

curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");
curl_setopt($ch, CURLOPT_TIMEOUT, 600);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, array("Content-Type: application/json", "Authorization: Bearer " . json_decode($token)));

$result = curl_exec($ch);

$json = json_decode($result, true);

echo "\n\ndate: ".$date."\n\n";

$products = array();
//print_r($json);
if(is_array($json) && (count($json) > 0)){
	echo count($json['items'])." records to add/update\n\n";
	foreach($json['items'] as $item):

		$product = array();

	 	$product['name'] = $item['name'];
	 	$product['status'] = $item['status'];

	 	if(isset($item['price'])){
	 		$product['price'] = $item['price'];	
	 	} else {
	 		$product['price'] = 0;
	 	}
	 	
	 	$custom_att = $item['custom_attributes'];
	 	$product['type_id'] = $item['type_id'];

	 	$sku = $item['sku'];
	 	$product['sku'] = $sku;

	 	foreach($custom_att as $att){

		
	 		if($att['attribute_code'] == 'short_description'){
	 			$short_description = $att['value'];
	 			$product['short_description'] = $short_description;
	 		}

	 		if($att['attribute_code'] == 'description'){
	 			$description = $att['value'];
	 			$product['description'] = $description;
	 		}

	 		if($att['attribute_code'] == 'product_downloads'){
	 			$downloads = $att['value'];
	 			$product['downloads'] = $downloads;
	 		}

	 		if($att['attribute_code'] == 'product_specifications'){
	 			$specs = $att['value'];
	 			$product['specs'] = $specs;
	 		}

	 	}


	 	if(!isset($product['short_description'])){
	 		$product['short_description'] = "";
	 	}

	 	if(!isset($product['description'])){
	 		$product['description'] = "";
	 	}

	 	if(!isset($product['product_specifications'])){
	 		$product['product_specifications'] = "";
	 	}


			  	/// GET IMAGE URL 
		  	$gallery = array();
		  	$ch = curl_init("$url/index.php/rest/V1/getproductimage/$msku");
			curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");
			curl_setopt($ch, CURLOPT_TIMEOUT, 600);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($ch, CURLOPT_HTTPHEADER, array("Content-Type: application/json", "Authorization: Bearer " . json_decode($token)));

			$img_result = curl_exec($ch);
			// print_r($img_result);

			$img_json = json_decode($img_result, true);
			if(isset($img_json[0]['product_image_url'])){
				$baseimg = $img_json[0]['product_image_url'];
			} else { 
				$baseimg = "";
			}

			$last = strrpos($baseimg, "/");
			$next_to_last = strrpos($baseimg, "/", $last - strlen($baseimg) - 1) - 2;
			$baseurl = substr($baseimg, 0, $next_to_last);

			$gallery = $item['media_gallery_entries'];
			$gallery_items = array();

			foreach ($gallery as $key => $entry) {
		    	$galurl = $baseurl . $entry['file'];
		        $gallery[$key]['file'] = $galurl;
			}

			$product['gallery'] = $gallery;

		  	/// GET STOCK QUANTITY

		   	$ch = curl_init("$url/index.php/rest/V1/stockItems/$msku");
			 curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");
			 curl_setopt($ch, CURLOPT_TIMEOUT, 600);
			 curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			 curl_setopt($ch, CURLOPT_HTTPHEADER, array("Content-Type: application/json", "Authorization: Bearer " . json_decode($token)));
		 
			 $inv_result = curl_exec($ch);
			 $sku_json = json_decode($inv_result, true);

			 if(isset($sku_json['qty'])){
			 	$qty = $sku_json['qty'];	
			 } else {
			 	$qty = 0;
			 }

			 $product['qty'] = $qty;
		 	
		 	/// GET RELATED ARRAY & ASSOCIATED(GROUPED SKUS) ARRAY 
		 	$related = $item['product_links'];
		 	$relArray = array();
		 	$assArray = array();
		 	foreach($related as $relsku){
		 		if($relsku['link_type'] == 'associated'){
		 			array_push($assArray, $relsku['linked_product_sku']);	
		 		}
		 		if($relsku['link_type'] == 'related'){
		 			array_push($relArray, $relsku['linked_product_sku']);
		 		}
		 	}

		 	$product['associated'] = $assArray;
		 	$product['related'] = $relArray;

           // GET CATEGORIES IN ARRAY
		 	$categories = $item['extension_attributes']['category_links'];
			$catArray = array();

		 	foreach($categories as $category){
		 		$category_id = $category['category_id'];
		//		echo "category id: " .$category_id;

		 		foreach($localCategories as $lcat){
		 			if($category_id == $lcat['baseId']){
		 				array_push($catArray, $lcat['id']);
		 			}
		 		}
		 	}

		 	$product['categories'] = $catArray;

		 	if(sizeof($catArray)>0){
				array_push($products, $product);
		 	}
	
//foreach($products as $product){	
//	 print_r($product);
	$__product = $objectManager->get('Magento\Catalog\Model\Product');

	if($__product->getIdBySku($product['sku'])) {
		$pid = $__product->getIdBySku($product['sku']);
	    echo '\nproduct '. $product['sku'] .' exists';
		
		$newurlkey = "sku-".strtolower($product['sku'])."-".strtolower($product['name']);
		
		$productFactory = $objectManager->get('\Magento\Catalog\Model\ResourceModel\Product\Action');
		$productFactory->updateAttributes([$pid], ['name' => $product['name']], 1);
		$productFactory->updateAttributes([$pid], ['description' => $product['description']], 1);
		$productFactory->updateAttributes([$pid], ['short_description' => $product['short_description']], 1); 
		$productFactory->updateAttributes([$pid], ['price' => $product['price']], 1);

		
		
		
		$categoryLinkRepository = $objectManager->get('\Magento\Catalog\Api\CategoryLinkManagementInterface');
		$categoryLinkRepository->assignProductToCategories($product['sku'], $product['categories']);
		

	
		$objectManager = \Magento\Framework\App\ObjectManager::getInstance();
		$pr = $objectManager->create('Magento\Catalog\Model\Product')->load($__product->getIdBySku($product['sku']));

		$productRepository = $objectManager->create('Magento\Catalog\Api\ProductRepositoryInterface');
		$productGallery = $objectManager->create('\Magento\Catalog\Model\ResourceModel\Product\Gallery');

		$pr->setQuantityAndStockStatus(['qty' => 100, 'is_in_stock' => 1]);
		

		$pr->save();
		echo '\nproduct udpated\n';

	} else {

		$fileSystem = $objectManager->create('\Magento\Framework\Filesystem');

		$tmpDir = $fileSystem->getDirectoryRead(\Magento\Framework\App\Filesystem\DirectoryList::MEDIA)->getAbsolutePath() . 'tmp' . DIRECTORY_SEPARATOR;
		
		

		$file = $objectManager->create('\Magento\Framework\Filesystem\Io\File');
		$file->checkAndCreateFolder($tmpDir);

		$primgs = $product['gallery'];

		$urlkey = "sku-".strtolower($product['sku'])."-".strtolower($product['name']);
		$_product = $objectManager->create('Magento\Catalog\Model\Product');

		$_product->setName($product['name']);
		$_product->setDescription($product['description']);
		$_product->setShortDescription($product['short_description']);
		$_product->setTypeId($product['type_id']);
		$_product->setSku($product['sku']);
		$_product->setPrice($product['price']);
		$_product->setUrlKey($urlkey);
		$_product->setWebsiteIds(array(1));
		$_product->setVisibility(4);
		$_product->setStatus($product['status']);
		$_product->setAttributeSetId(4);
		$category_id= array(5);
		$_product->setCategoryIds($product['categories']);
	
		
		$_product->setQuantityAndStockStatus(['qty' => 100, 'is_in_stock' => 1]);


		foreach($primgs as $primg){
		 	$imageUrl = $primg['file'];
	        $newFileName = $tmpDir . baseName($imageUrl);
	        $imageType = $primg['types'];

	        $result = $file->read($imageUrl, $newFileName);
	        if ($result) {
	        	echo $imageUrl;
	            $_product->addImageToMediaGallery($newFileName, $imageType, true, false);
	        }
		}

		echo "\n" . $product['sku'] . " created";
		echo "\n";
		$_product->save();
	//}
}
		endforeach;
} else {
	echo "No recently updated products";
}


$obj                      = \Magento\Framework\App\ObjectManager::getInstance();
$indexerCollectionFactory = $obj->get("\Magento\Indexer\Model\Indexer\CollectionFactory");
$indexerFactory           = $obj->get("\Magento\Indexer\Model\IndexerFactory");
$indexerCollection        = $indexerCollectionFactory->create();
$allIds                   = $indexerCollection->getAllIds();

foreach ($allIds as $id)
{
    $indexer = $indexerFactory->create()->load($id);
    if($indexer->getStatus() != 'valid'){
    	$indexer->reindexRow($id);
    }
}


function getLocalCategories(){

	$localCategories = array();
	
	$objectManager = \Magento\Framework\App\ObjectManager::getInstance();
	$categoryCollection = $objectManager->get('\Magento\Catalog\Model\ResourceModel\Category\CollectionFactory'); // 
	$categories = $categoryCollection->create();
	$categories->addAttributeToSelect('*');
	 
	foreach ($categories as $category) {
		$localCat = array();

		$localCat['id'] = $category->getId();
		$localCat['baseId'] = $category->getBaseCatId();
	    //print_r($category->getData()); 

	    $cat_ids = $category->getPath();
		$lcatIds = explode('/', $cat_ids);
		// $catIds = array_shift($catIds2);
		array_shift($lcatIds);
			
		$lcatTree = "";

		foreach($lcatIds as $lcatId){
			if($lcatTree != ""){
				$lcatTree .= "/";
			}

		    $_objectManager = \Magento\Framework\App\ObjectManager::getInstance();
            $category_data = $_objectManager->create('Magento\Catalog\Model\Category')
                    ->load($lcatId);
            $lcat_name = $category_data->getName();

			$lcatTree .= $lcat_name;
		}

		$localCat['name'] = $lcatTree;
		array_push($localCategories, $localCat);

	}
	return $localCategories;
	print_r($localCategories);
}

function getCategoryName($catid, $token, $url){
	$ch = curl_init("$url/index.php/rest/V1/categories/$catid");
	curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");
	curl_setopt($ch, CURLOPT_TIMEOUT, 600);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_HTTPHEADER, array("Content-Type: application/json", "Authorization: Bearer " . json_decode($token)));

	$result = curl_exec($ch);
	$json = json_decode($result, true);

	$cat_name = $json['name'];

	return $cat_name;
}

function getLocalCatId($catTree, $localCategories){
	foreach($localCategories as $cat){
		if($cat['name'] == $catTree){
			return $cat['id'];
		}
	}
}