<?php

/**
 * Barcode Buddy for Grocy
 *
 * PHP version 7
 *
 * API controller
 *
 * LICENSE: This source file is subject to version 3.0 of the GNU General
 * Public License v3.0 that is attached to this project.
 *
 * @author     Marc Ole Bulling
 * @copyright  2019 Marc Ole Bulling
 * @license    https://www.gnu.org/licenses/gpl-3.0.en.html  GNU GPL v3.0
 * @since      File available since Release 1.4
 */


require_once __DIR__ . "/../incl/configProcessing.inc.php";
require_once __DIR__ . "/../incl/db.inc.php";
require_once __DIR__ . "/../incl/processing.inc.php";
require_once __DIR__ . "/../incl/config.inc.php";

//removes Get parameters
$requestedUrl = strtok($_SERVER["REQUEST_URI"], '?');

//removes everything before "/api"
$requestedUrl = trim(substr($requestedUrl, strpos($requestedUrl, '/api')));

$api = new BBuddyApi();
if ($requestedUrl == "/api/") {
    readfile(__DIR__ . "/doc.html");
    die();
}
if ($CONFIG->REQUIRE_API_KEY)
    $api->checkIfAuthorized();
$api->execute($requestedUrl);


class BBuddyApi {

    private $routes = array();

    /**
     * Checks if authorized
     * @return bool True if authorized, or dies if not
     * @throws DbConnectionDuringEstablishException
     */
    function checkIfAuthorized(): bool {
        global $CONFIG;

        if ($CONFIG->checkIfAuthenticated(false))
            return true;

        $apiKey = "";
        if (isset($_SERVER["HTTP_BBUDDY_API_KEY"]))
            $apiKey = $_SERVER["HTTP_BBUDDY_API_KEY"];
        if (isset($_GET["apikey"]))
            $apiKey = $_GET["apikey"];

        if ($apiKey == "")
            self::sendUnauthorizedAndDie();

        if (DatabaseConnection::getInstance()->isValidApiKey($apiKey))
            return true;
        else
            self::sendUnauthorizedAndDie();
        return false;
    }

    static function sendUnauthorizedAndDie(): void {
        self::sendResult(self::createResultArray(null, "Unauthorized", 401), 401);
        die();
    }

    function execute(string $url): void {
        global $CONFIG;

        //Turn off all error reporting, as it could cause problems with parsing json clientside
        if (!$CONFIG->IS_DEBUG)
            error_reporting(0);

        if (!isset($this->routes[$url])) {
            self::sendResult(self::createResultArray(null, "API call not found", 404), 404);
        } else {
            $this->routes[$url]->execute();
        }
    }


    function __construct() {
        $this->initRoutes();
    }


    /**
     * @param array|null $data
     * @param string $result
     * @param int $http_int
     * @return array (array|mixed)[]
     */
    static function createResultArray(array $data = null, string $result = "OK", int $http_int = 200): array {
        return array(
            "data" => $data,
            "result" => array(
                "result" => $result,
                "http_code" => $http_int
            )
        );
    }

    function addRoute(ApiRoute $route): void {
        $this->routes[$route->path] = $route;
    }

    private function initRoutes(): void {

        $this->addRoute(new ApiRoute("/action/scan", function () {
            $barcode = "";
            if (isset($_GET["text"]))
                $barcode = $_GET["text"];
            if (isset($_GET["add"]))
                $barcode = $_GET["add"];
            if (isset($_POST["barcode"]))
                $barcode = $_POST["barcode"];
            if ($barcode == "")
                return self::createResultArray(null, "No barcode supplied", 400);
            else {
                $bestBefore = null;
                $price      = null;
                if (isset($_POST["bestBeforeInDays"]) && $_POST["bestBeforeInDays"] != null) {
                    if (is_numeric($_POST["bestBeforeInDays"]))
                        $bestBefore = $_POST["bestBeforeInDays"];
                    else
                        return self::createResultArray(null, "Invalid parameter bestBeforeInDays: needs to be type int", 400);
                }
                if (isset($_POST["price"]) && $_POST["price"] != null) {
                    if (is_numeric($_POST["price"]))
                        $price = $_POST["price"];
                    else
                        return self::createResultArray(null, "Invalid parameter price: needs to be type float", 400);
                }
                $result = processNewBarcode(sanitizeString($barcode), $bestBefore, $price);
                return self::createResultArray(array("result" => sanitizeString($result)));
            }
        }));

        $this->addRoute(new ApiRoute("/state/getmode", function () {
            return self::createResultArray(array(
                "mode" => DatabaseConnection::getInstance()->getTransactionState()
            ));
        }));

        $this->addRoute(new ApiRoute("/state/setmode", function () {
            $state = null;
            if (isset($_GET["state"]))
                $state = $_GET["state"];
            else if (isset($_POST["state"]))
                $state = $_POST["state"];            

            //Also check if value is a valid range (STATE_CONSUME the lowest and STATE_CONSUME_ALL the highest value)
            if (!is_numeric($state) || $state < STATE_CONSUME || $state > STATE_CONSUME_ALL)
                return self::createResultArray(null, "Invalid state provided", 400);
            else {
                DatabaseConnection::getInstance()->setTransactionState(intval($state));
                return self::createResultArray();
            }
        }));

        $this->addRoute(new ApiRoute("/system/barcodes", function () {
            $config = BBConfig::getInstance();
            return self::createResultArray(array(
                "BARCODE_C" => $config["BARCODE_C"],
                "BARCODE_CS" => $config["BARCODE_CS"],
                "BARCODE_P" => $config["BARCODE_P"],
                "BARCODE_O" => $config["BARCODE_O"],
                "BARCODE_GS" => $config["BARCODE_GS"],
                "BARCODE_Q" => $config["BARCODE_Q"],
                "BARCODE_AS" => $config["BARCODE_AS"],
                "BARCODE_CA" => $config["BARCODE_CA"]
            ));
        }));

        $this->addRoute(new ApiRoute("/system/info", function () {
            return self::createResultArray(array(
                "version" => BB_VERSION_READABLE,
                "version_int" => BB_VERSION
            ));
        }));

        $this->addRoute(new ApiRoute("/system/unknownbarcodes", function () {
            $barcodes = DatabaseConnection::getInstance()->getStoredBarcodes();
            $unknownBarcodes = $barcodes["unknown"];

            // Check if lookup parameter is set
            $doLookup = false;
            if (isset($_GET["lookup"]) && ($_GET["lookup"] === "true" || $_GET["lookup"] === "1"))
                $doLookup = true;

            return self::createResultArray(array(
                "count" => count($unknownBarcodes),
                "barcodes" => array_map(function($item) use ($doLookup) {
                    $result = array(
                        "barcode" => $item['barcode'],
                        "amount" => $item['amount']
                    );

                    if ($doLookup) {
                        $result["product_info"] = self::lookupOpenFoodFacts($item['barcode']);
                    }

                    return $result;
                }, $unknownBarcodes)
            ));
        }));

        $this->addRoute(new ApiRoute("/action/deleteunknown", function () {
            $barcode = null;
            if (isset($_GET["barcode"]))
                $barcode = $_GET["barcode"];
            if (isset($_POST["barcode"]))
                $barcode = $_POST["barcode"];

            if ($barcode === null || $barcode === "")
                return self::createResultArray(null, "No barcode supplied", 400);

            $deleted = DatabaseConnection::getInstance()->deleteUnknownBarcode(sanitizeString($barcode));

            if ($deleted) {
                return self::createResultArray(array("deleted" => true));
            } else {
                return self::createResultArray(null, "Barcode not found in unknown list", 404);
            }
        }));

        $this->addRoute(new ApiRoute("/action/associatebarcode", function () {
            // Get parameters from POST body (JSON) or form data
            $barcode = null;
            $productId = null;

            // Try JSON body first
            $jsonBody = file_get_contents('php://input');
            if ($jsonBody) {
                $jsonData = json_decode($jsonBody, true);
                if ($jsonData) {
                    $barcode = $jsonData['barcode'] ?? null;
                    $productId = $jsonData['product_id'] ?? null;
                }
            }

            // Fall back to POST/GET params
            if ($barcode === null && isset($_POST["barcode"]))
                $barcode = $_POST["barcode"];
            if ($productId === null && isset($_POST["product_id"]))
                $productId = $_POST["product_id"];

            // Validate required params
            if ($barcode === null || $barcode === "")
                return self::createResultArray(null, "No barcode supplied", 400);
            if ($productId === null || !is_numeric($productId))
                return self::createResultArray(null, "No valid product_id supplied", 400);

            $barcode = sanitizeString($barcode);
            $productId = intval($productId);

            // Check barcode exists in unknown list and get its amount
            $db = DatabaseConnection::getInstance();
            $storedAmount = $db->getStoredBarcodeAmount($barcode);
            if ($storedAmount == 0) {
                // Check if barcode exists but with 0 amount (shouldn't happen, but check anyway)
                if (!$db->isUnknownBarcodeAlreadyStored($barcode)) {
                    return self::createResultArray(null, "Barcode not found in unknown list", 404);
                }
            }

            // Verify product exists in Grocy
            $productInfo = API::getProductInfo($productId);
            if ($productInfo === null)
                return self::createResultArray(null, "Product not found in Grocy", 404);

            // Add barcode to Grocy product
            try {
                API::addBarcode($productId, $barcode, null);
            } catch (Exception $e) {
                return self::createResultArray(null, "Failed to add barcode to Grocy: " . $e->getMessage(), 500);
            }

            // Add stock if amount > 0
            $stockAdded = 0;
            if ($storedAmount > 0) {
                try {
                    API::purchaseProduct($productId, floatval($storedAmount));
                    $stockAdded = $storedAmount;
                } catch (Exception $e) {
                    // Log but don't fail - barcode was already associated
                    $db->saveLog("Warning: Barcode associated but stock add failed: " . $e->getMessage(), false, true);
                }
            }

            // Delete from unknown list
            $db->deleteUnknownBarcode($barcode);

            return self::createResultArray(array(
                "barcode" => $barcode,
                "product_id" => $productId,
                "product_name" => $productInfo->name,
                "stock_added" => $stockAdded
            ));
        }));

        $this->addRoute(new ApiRoute("/action/createandassociate", function () {
            // Get parameters from POST body (JSON)
            $barcode = null;
            $name = null;
            $productGroupId = null;
            $locationId = null;

            // Parse JSON body
            $jsonBody = file_get_contents('php://input');
            if ($jsonBody) {
                $jsonData = json_decode($jsonBody, true);
                if ($jsonData) {
                    $barcode = $jsonData['barcode'] ?? null;
                    $name = $jsonData['name'] ?? null;
                    $productGroupId = $jsonData['product_group_id'] ?? null;
                    $locationId = $jsonData['location_id'] ?? null;
                }
            }

            // Validate required params
            if ($barcode === null || $barcode === "")
                return self::createResultArray(null, "No barcode supplied", 400);
            if ($name === null || $name === "")
                return self::createResultArray(null, "No product name supplied", 400);

            $barcode = sanitizeString($barcode);
            $name = sanitizeString($name);

            // Check barcode exists in unknown list and get its amount
            $db = DatabaseConnection::getInstance();
            $storedAmount = $db->getStoredBarcodeAmount($barcode);
            if ($storedAmount == 0) {
                if (!$db->isUnknownBarcodeAlreadyStored($barcode)) {
                    return self::createResultArray(null, "Barcode not found in unknown list", 404);
                }
            }

            // Build product data for Grocy
            $productData = array(
                "name" => $name,
                "active" => 1
            );

            if ($locationId !== null && is_numeric($locationId)) {
                $productData["location_id"] = intval($locationId);
            }

            if ($productGroupId !== null && is_numeric($productGroupId)) {
                $productData["product_group_id"] = intval($productGroupId);
            }

            // Create product in Grocy
            $newProductId = null;
            try {
                $curl = new CurlGenerator(API_O_PRODUCTS, METHOD_POST, json_encode($productData));
                $result = $curl->execute(true);
                if (isset($result["created_object_id"])) {
                    $newProductId = intval($result["created_object_id"]);
                } else {
                    return self::createResultArray(null, "Failed to create product in Grocy: no ID returned", 500);
                }
            } catch (Exception $e) {
                return self::createResultArray(null, "Failed to create product in Grocy: " . $e->getMessage(), 500);
            }

            // Add barcode to the new product
            try {
                API::addBarcode($newProductId, $barcode, null);
            } catch (Exception $e) {
                // Product was created but barcode add failed - log but continue
                $db->saveLog("Warning: Product created but barcode add failed: " . $e->getMessage(), false, true);
            }

            // Add stock if amount > 0
            $stockAdded = 0;
            if ($storedAmount > 0) {
                try {
                    API::purchaseProduct($newProductId, floatval($storedAmount));
                    $stockAdded = $storedAmount;
                } catch (Exception $e) {
                    // Log but don't fail
                    $db->saveLog("Warning: Product created but stock add failed: " . $e->getMessage(), false, true);
                }
            }

            // Delete from unknown list
            $db->deleteUnknownBarcode($barcode);

            return self::createResultArray(array(
                "barcode" => $barcode,
                "product_id" => $newProductId,
                "product_name" => $name,
                "stock_added" => $stockAdded
            ));
        }));
    }


    /**
     * @return never
     */
    static function sendResult(array $data, int $result): void {
        header('Content-Type: application/json');
        http_response_code($result);
        echo trim(json_encode($data, JSON_HEX_QUOT));
        die();
    }

    /**
     * Lookup a barcode on Open Food Facts and return product info
     * @param string $barcode The barcode to lookup
     * @return array|null Product info array or null if not found
     */
    static function lookupOpenFoodFacts(string $barcode): ?array {
        $url = "https://world.openfoodfacts.org/api/v0/product/" . urlencode($barcode) . ".json";

        try {
            $curl = new CurlGenerator($url, METHOD_GET, null, null, true);
            $result = $curl->execute(true);
        } catch (Exception $e) {
            return null;
        }

        if (!isset($result["status"]) || $result["status"] !== 1) {
            return null;
        }

        $product = $result["product"] ?? array();

        // Extract product name (try language-specific first, then generic)
        $name = null;
        if (!empty($product["product_name_en"])) {
            $name = $product["product_name_en"];
        } elseif (!empty($product["product_name"])) {
            $name = $product["product_name"];
        } elseif (!empty($product["generic_name"])) {
            $name = $product["generic_name"];
        }

        // Extract brand
        $brand = $product["brands"] ?? null;

        // Extract image URL (prefer front image)
        $imageUrl = null;
        if (!empty($product["image_front_url"])) {
            $imageUrl = $product["image_front_url"];
        } elseif (!empty($product["image_url"])) {
            $imageUrl = $product["image_url"];
        }

        // Return null if no useful info found
        if ($name === null && $brand === null && $imageUrl === null) {
            return null;
        }

        return array(
            "name" => $name,
            "brand" => $brand,
            "image_url" => $imageUrl
        );
    }

}


class ApiRoute {

    public $path;
    private $function;

    /**
     * @param string $path API path
     */
    function __construct(string $path, $function) {
        $this->path     = '/api' . $path;
        $this->function = $function;
    }

    function execute(): void {
        $result = $this->function->__invoke();
        BBuddyApi::sendResult($result, $result["result"]["http_code"]);
    }
}
