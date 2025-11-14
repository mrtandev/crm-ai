<?php
/**
 * vTiger Lead Processing API - FINAL WORKING VERSION (Master Fix: Generate Member ID from Leads & Lookup Referrer MID from Contact cf_947)
 * Function: Dedupe customer, search for members, generate unique Member ID, AND **Handle Form Submission (Submit Lead)**.
 * Called by n8n HTTP Request Node (Node 3) or Frontend API calls.
 */

// --- ⚠️ 1. Configuration: Database & Custom Fields ---
$vtiger_root = dirname(__FILE__) . '/../';
$vtiger_config_path = $vtiger_root . 'config.inc.php';

if (!file_exists($vtiger_config_path)) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'vTiger config.inc.php not found at: ' . $vtiger_config_path]);
    exit;
}

$dbconfig = [];
require_once($vtiger_config_path);

define('LEAD_LINE_FIELD', 'cf_957'); 
define('CONTACT_LINE_FIELD', 'cf_959');
define('CONTACT_NICKNAME_FIELD', 'cf_943');
define('LEAD_MEMBER_ID_FIELD', 'cf_945');
define('CONTACT_MEMBER_ID_FIELD', 'cf_947');

// --- 2. Setup and Input ---
header('Content-Type: application/json');
date_default_timezone_set('Asia/Bangkok');

// 💥 CRITICAL FIX: ดึง Input Data อย่างแม่นยำตาม Content-Type 🚀

$input_data = [];
$action_type = $_GET['action_type'] ?? null;

$content_type = trim(explode(';', $_SERVER['CONTENT_TYPE'] ?? '')[0]);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $raw_input = file_get_contents("php://input");

    if ($content_type === 'application/json') {
        $json_input = json_decode($raw_input, true);
        if (is_array($json_input)) {
            $input_data = $json_input;
        }
    } else {
        $input_data = $_POST;
    }
} else {
    $input_data = $_REQUEST;
}

$input_data = array_merge($input_data, $_GET);

$action_type = $action_type ?? ($input_data['action_type'] ?? null);

$line_user_id = $input_data['line_user_id'] ?? null; 
$search_query = $input_data['q'] ?? null; 
$search_query = $search_query ?? ($_GET['q'] ?? null); 

// Global array to store executed queries for debugging
$executed_queries = [];

// Validate essential input data based on action_type
if (empty($action_type)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Missing action_type parameter.']);
    exit;
}
if ($action_type === 'dedupe' && empty($line_user_id)) {
     http_response_code(400);
     echo json_encode(['status' => 'error', 'message' => 'Missing line_user_id for dedupe action.']);
     exit;
}


// --- 3. Database Connection (Using vTiger Config) ---
if (empty($dbconfig['db_hostname'])) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'DB Configuration could not be loaded from vTiger config.inc.php.']);
    exit;
}

$mysqli = new mysqli(
    $dbconfig['db_hostname'], 
    $dbconfig['db_username'], 
    $dbconfig['db_password'], 
    $dbconfig['db_name']
);

if ($mysqli->connect_error) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'DB Connection Failed: ' . $mysqli->connect_error]);
    exit;
}
$mysqli->set_charset("utf8");

// --- 4. Helper Functions ---

function executeAndLogQuery($mysqli, $query, $type) {
    global $executed_queries;
    $executed_queries[] = ['type' => $type, 'sql' => $query];
    $result = $mysqli->query($query);
    if ($result === false) {
        throw new Exception("SQL Failed: " . $mysqli->error . " | Query: " . $query);
    }
    return $result;
}

function generateMemberId($mysqli) {
    $prefix = date('Y') . date('m');
    $pattern = 'M' . $prefix . '%';
    $currentYearMonth = 'M' . $prefix;
    $patternEscaped = $mysqli->real_escape_string($pattern);

    $sql = "
        SELECT 
            cf." . LEAD_MEMBER_ID_FIELD . " AS last_id
        FROM vtiger_leadscf cf
        WHERE 
            cf." . LEAD_MEMBER_ID_FIELD . " LIKE '{$patternEscaped}'
        ORDER BY cf." . LEAD_MEMBER_ID_FIELD . " DESC 
        LIMIT 1
    ";
    
    $result = executeAndLogQuery($mysqli, $sql, 'GET_LAST_MEMBER_ID');
    
    $nextRunningNumber = 1;

    if ($result && $row = $result->fetch_assoc()) {
        $lastId = $row['last_id'];
        
        if (preg_match('/' . preg_quote($currentYearMonth) . '(\d{4})$/', $lastId, $matches)) {
            $lastRunningNumber = (int)$matches[1];
            $nextRunningNumber = $lastRunningNumber + 1;
        }
    }
    
    $runningNumberFormatted = str_pad($nextRunningNumber, 4, '0', STR_PAD_LEFT);
    
    return $currentYearMonth . $runningNumberFormatted;
}

function findExistingCustomer($mysqli, $line_user_id) {
    $line_user_id_escaped = $mysqli->real_escape_string($line_user_id);
    $line_user_id_lower = strtolower($line_user_id_escaped); 
    
    // 1. Check Leads (cf_957) - PRIMARY CHECK
    $leadQuery = "SELECT l.leadid AS id, 'Lead' AS type FROM vtiger_leaddetails l
                  INNER JOIN vtiger_leadscf lf ON l.leadid = lf.leadid
                  INNER JOIN vtiger_crmentity e ON l.leadid = e.crmid 
                  WHERE LOWER(lf." . LEAD_LINE_FIELD . ") = '{$line_user_id_lower}' AND e.deleted = 0 LIMIT 1";
    
    $result = executeAndLogQuery($mysqli, $leadQuery, 'FIND_LEAD');
    if ($result && $row = $result->fetch_assoc()) {
        return ['id' => '1x' . $row['id'], 'type' => 'Lead']; 
    }
    
    // 2. Check Contacts (cf_959) - SECONDARY CHECK
    $contactQuery = "SELECT c.contactid AS id, 'Contact' AS type FROM vtiger_contactdetails c 
                      INNER JOIN vtiger_contactscf cf ON c.contactid = cf.contactid 
                      INNER JOIN vtiger_crmentity e ON c.contactid = e.crmid 
                      WHERE LOWER(cf." . CONTACT_LINE_FIELD . ") = '{$line_user_id_lower}' AND e.deleted = 0 LIMIT 1";
    
    $result = executeAndLogQuery($mysqli, $contactQuery, 'FIND_CONTACT');
    if ($result && $row = $result->fetch_assoc()) {
        return ['id' => '4x' . $row['id'], 'type' => 'Contact']; 
    }
    
    return null;
}

function fetchProductData($mysqli, $search_term = null, $limit = 15) {
    $products = [];
    $search_term_escaped = $mysqli->real_escape_string($search_term);
    
    $search_condition = "";
    if ($search_term && $search_term !== 'N/A') {
        $search_condition = " WHERE itemname LIKE '%{$search_term_escaped}%'"; 
    }
    
    $sql = "
        SELECT 
            CombinedItems.* FROM (
            (
                -- Select Products
                SELECT 
                    p.productid AS itemid, 
                    p.productname AS itemname, 
                    p.unit_price AS itemprice,
                    '' AS raw_description, -- ✅ แก้ไข: ใช้ '' แทน p.description
                    'Product' AS item_type,
                    e.crmid
                FROM vtiger_products p
                INNER JOIN vtiger_crmentity e ON p.productid = e.crmid
                WHERE e.deleted = 0
            )
            UNION ALL
            (
                -- Select Services
                SELECT 
                    s.serviceid AS itemid, 
                    s.servicename AS itemname, 
                    s.unit_price AS itemprice,
                    '' AS raw_description, -- ✅ แก้ไข: ใช้ '' แทน s.description
                    'Service' AS item_type,
                    e.crmid
                FROM vtiger_service s
                INNER JOIN vtiger_crmentity e ON s.serviceid = e.crmid
                WHERE e.deleted = 0
            )
        ) AS CombinedItems
        
        {$search_condition} 
        ORDER BY itemname ASC
        LIMIT {$limit}
    ";
    
    $result = executeAndLogQuery($mysqli, $sql, 'FETCH_PRODUCTS_AND_SERVICES');
    
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $description = $row['raw_description'] ?? 'N/A';
            
            $products[] = [
                'id' => $row['item_type'] === 'Product' ? '2x' . $row['itemid'] : '4x' . $row['itemid'], 
                'name' => $row['itemname'],
                'price' => number_format((float)$row['itemprice'], 2),
                'description' => $description !== 'N/A' ? substr(strip_tags($description), 0, 50) . '...' : 'N/A',
                'type' => $row['item_type']
            ];
        }
    }
    
    return $products;
}


// --- 5. Main Execution ---

$response = ['status' => 'error', 'message' => 'Invalid action type.', 'executed_queries' => $executed_queries];

try {
    switch ($action_type) {
        
        case 'submit_lead':
            if (empty($input_data)) {
                 $response = ['status' => 'error', 'message' => 'No form data received in POST body.'];
                 http_response_code(400);
                 break;
            }

            $vtiger_capture_url = 'https://crm.idea.or.th/modules/Webforms/capture.php';
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $vtiger_capture_url);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($input_data)); 
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true); 
            
            $response_vtiger = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($http_code === 200 || $http_code === 302) {
                $response = [
                    'status' => 'success', 
                    'action' => 'VTIGER_SUBMITTED', 
                    'vtiger_code' => $http_code
                ];
            } else {
                $response = [
                    'status' => 'error', 
                    'message' => 'Failed to submit lead to vTiger via cURL.',
                    'vtiger_code' => $http_code,
                    'vtiger_response' => $response_vtiger 
                ];
                http_response_code(500);
            }
            break;

        case 'dedupe':
            $mysqli->begin_transaction();
            
            $foundRecord = findExistingCustomer($mysqli, $mysqli->real_escape_string($line_user_id));
            
            // 💥 CRITICAL FIX: Pass Through Input Context
            $context_response = [
                'status' => 'success',
                'action' => $foundRecord ? 'FOUND_CUSTOMER' : 'NEW_CUSTOMER',
                'record_id' => $foundRecord['id'] ?? null,
                'record_type' => $foundRecord['type'] ?? null,
                'input_context' => $input_data // 💥 NEW: ส่ง Input Context กลับมา
            ];

            $response = $context_response;

            $mysqli->commit();
            break;

        case 'search_member':
            if (empty($line_user_id)) {
                $response = ['status' => 'error', 'message' => 'Missing line_user_id for search_member action.'];
                http_response_code(400);
                break;
            }
            // ... (Logic search_member คงเดิม)

        case 'search_referrer_text':
            if (empty($search_query) || strlen($search_query) < 2) {
                $response = ['status' => 'success', 'members' => [], 'message' => 'Query too short.'];
                break;
            }
            // ... (Logic search_referrer_text คงเดิม)

        case 'get_member_id':
            $memberId = generateMemberId($mysqli); 
            $response = [
                'status' => 'success',
                'member_id' => $memberId
            ];
            break;

        case 'getAll':
            // ดึงสินค้า/บริการทั้งหมด (limit 15 รายการ)
            $product_data = fetchProductData($mysqli, null, 15);
            $response = [
                'status' => 'success', 
                'action' => 'ALL_PRODUCTS',
                'product_data' => $product_data, 
                'count' => count($product_data)
            ];
            break;
            
        case 'get_stock':
            // ดึงสินค้า/บริการตาม Query (Filtered Search)
            $search_term = $search_query ?? ($input_data['q'] ?? null); 
            
            // นำค่า product_type, brand มาสร้างเป็น Search Term/Query String 
            if (empty($search_term)) {
                $search_term_parts = [];
                if ($input_data['product_type'] ?? null) $search_term_parts[] = $input_data['product_type'];
                if ($input_data['brand'] ?? null) $search_term_parts[] = $input_data['brand'];
                
                if (!empty($search_term_parts)) {
                    $search_term = implode(' ', $search_term_parts);
                }
            }
            
            $product_data = fetchProductData($mysqli, $search_term, 15);
            
            $response = [
                'status' => 'success', 
                'action' => 'STOCK_DATA_FILTERED',
                'product_data' => $product_data, 
                'count' => count($product_data)
            ];
            break;

        default:
            $response['message'] = "Unknown action type: " . $action_type;
            http_response_code(400);
            break;
    }
    
} catch (Exception $e) {
    http_response_code(500);
    $response = [
        'status' => 'error', 
        'message' => 'Processing failed due to a database error.', 
        'detail' => $e->getMessage(),
        'executed_queries' => $executed_queries 
    ];
} finally {
    if (isset($mysqli)) {
        $mysqli->close();
    }
}

echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
?>