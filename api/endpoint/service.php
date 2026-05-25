<?php
header('Content-Type: application/json');
require_once '../config/auth.php';
include '../config/connection.php';

/* 
    This file serves as the main endpoint for the API. 
    It routes requests to the appropriate controller based on the 'service' parameter in the query string. 
    It also handles authentication and input validation for each request.
*/

/* Fetch variables from the request */
$request = $_SERVER['REQUEST_METHOD']; // GET, POST, PUT, DELETE
$service = $_GET['service'] ?? null; // The specific service being requested (e.g., 'employee', 'product', 'sale')
$id = $_GET['id'] ?? null; // Optional ID parameter for specific resource retrieval or updates
$amount = $_GET['amount'] ?? null; // Optional amount parameter for incrementing/decrementing product stock
$input = json_decode(file_get_contents('php://input'), true); // For POST and PUT requests, decode the JSON payload into an associative array
$interval = $_GET['interval'] ?? null; // Optional interval parameter for daily report (e.g., 1, 7, 30 days, must be an integer)
$name = $_GET['name'] ?? null;

/* Route the request to the appropriate controller */
switch ($request) {
    case 'GET': // Handle GET requests
        switch ($service) {
            case 'employee': // Admin-only endpoint to retrieve employee information
                auth::requireAdmin(true);
                require_once '../controllers/ctrlEmployee.php';
                if (!isset($id)) { // If no ID is provided, return all employees
                    getEmployee($conn);
                } else { // If an ID is provided, return the specific employee
                    try {
                        getEmployeebyID($conn, $id);
                    } catch (InvalidArgumentException $e) { // Handle any exceptions thrown by the controller (e.g., invalid ID format)
                        http_response_code(400);
                        echo json_encode(["status" => "error", "message" => $e->getMessage()]);
                        exit();
                    }
                }
                break;
            case 'currentemployee': // Endpoint for employees to retrieve their own information
                auth::requireEmployee(true);
                require_once '../controllers/ctrlEmployee.php';
                getEmployeebyID($conn, $_SESSION['user_id']); // Use the user ID from the session to retrieve their own information
                break;
            case 'product': // Endpoint to retrieve product information, accessible by both admins and employees
                auth::requireEmployee(true);
                require_once '../controllers/ctrlProduct.php';
                if (!isset($id)) { // If no ID is provided, return all products
                    if (!isset($name)) {
                        getProduct($conn);
                    } else {
                        try {
                            getProductbyName($conn, $name);
                        } catch (Exception $e) {
                            http_response_code(400);
                            echo json_encode(["status" => "error", "message" => $e->getMessage()]);
                        }
                    }
                } else {
                    try {
                        getProductbyID($conn, $id);
                    } catch (InvalidArgumentException $e) {
                        http_response_code(400);
                        echo json_encode(["status" => "error", "message" => $e->getMessage()]);
                        exit();
                    }
                }
                break;
            case 'sale': // Admin-only endpoint to retrieve sale information    
                auth::requireAdmin(true);
                require_once '../controllers/ctrlSales.php';
                getSales($conn);
                break;
            case 'salesbyemployee': // Admin-only endpoint to retrieve sales by a specific employee
                auth::requireAdmin(true);
                require_once '../controllers/ctrlSales.php';
                if (!isset($id)) {
                    http_response_code(400);
                    echo json_encode(["status" => "error", "message" => "Please provide an employee ID"]);
                    exit();
                } else {
                    try {
                        getSalesByEmployeeID($conn, $id);
                    } catch (InvalidArgumentException $e) {
                        http_response_code(400);
                        echo json_encode(["status" => "error", "message" => $e->getMessage()]);
                        exit();
                    }
                }
                break;
            case 'salesbycurrentemployee': // Endpoint for employees to retrieve their own sales information
                auth::requireEmployee(true);
                require_once '../controllers/ctrlSales.php';
                getSalesByEmployeeID($conn, $_SESSION['user_id']); // Use the user ID from the session to retrieve their own sales information
                break;
            case 'dailyreport': // Admin-only endpoint to retrieve a daily report of sales within a specified interval
                auth::requireAdmin(true);
                require_once '../controllers/ctrlDailyReport.php';
                if (!isset($interval)) {
                    $interval = 1; // Default to 1 day if no interval is provided
                }
                getDailyReport($conn, $interval);
                break;
            default: // If the service parameter does not match any known service, return a 404 error
                http_response_code(404);
                echo json_encode(["status: " => "error", "message" => "Service not found"]);
                exit();
        }

        break;
    case 'POST': // Handle POST requests
        switch ($service) {
            case 'login': // Endpoint for user login, accessible by anyone
                require 'loginvalidation.php';
                break;
            case 'employee': // Admin-only endpoint to create a new employee
                auth::requireAdmin(true);
                require_once '../controllers/ctrlEmployee.php';
                validateJSON();
                inEmployee($conn, $input);
                break;
            case 'product': // Admin-only endpoint to create a new product
                auth::requireAdmin(true);
                require_once '../controllers/ctrlProduct.php';
                validateJSON();
                inProduct($conn, $input);
                break;
            case 'sale': // Endpoint for employees to record a new sale
                auth::requireEmployee(true);
                require_once '../controllers/ctrlSales.php';
                validateJSON();
                inSale($conn, $input);
                break;
            default: // If the service parameter does not match any known service, return a 404 error
                http_response_code(404);
                echo json_encode(["status: " => "error", "message" => "Service not found"]);
                exit();
        }
        break;
    case 'PUT': // Handle PUT requests for updating resources
        switch ($service) {
            case 'employee': // Admin-only endpoint to update an existing employee's information
                auth::requireAdmin(true);
                require_once '../controllers/ctrlEmployee.php';
                validateJSON();
                requiresID($id);
                updateEmployee($conn, $input, $id);
                break;
            case 'currentemployee': // Endpoint for employees to update their own information   
                auth::requireEmployee(true);
                require_once '../controllers/ctrlEmployee.php';
                validateJSON();
                updateEmployee($conn, $input, $_SESSION['user_id']);
                break;
            case 'product': // Admin-only endpoint to update an existing product's information
                auth::requireAdmin(true);
                require_once '../controllers/ctrlProduct.php';
                requiresID($id);
                updateProduct($conn, $input, $id);
                break;
            case 'incproduct': // Endpoint for employees to increase a product's quantity
                auth::requireEmployee(true);
                require_once '../controllers/ctrlProduct.php';
                requiresID($id);
                incProduct($conn, $id);
                break;
            case 'decproduct':  // Endpoint for employees to decrease a product's quantity
                auth::requireEmployee(true);
                require_once '../controllers/ctrlProduct.php';
                requiresID($id);
                decProduct($conn, $amount, $id);
                break;
            default: // If the service parameter does not match any known service, return a 404 error
                http_response_code(404);
                echo json_encode(["status: " => "error", "message" => "Service not found"]);
                exit();
        }
        break;
    case 'DELETE': // Handle DELETE requests for soft delete 
        auth::requireAdmin(true);
        requiresID($id);
        require_once "../controllers/SpringBootController.php";
        switch ($service) {
            case 'product': // Endponit to softdelete a product (sets state to discontinued)
                deleteProduct($id, $SpringBootBaseURL);
                break;
            case 'employee': // Endponit to softdelete a employee (sets state to discharged)
                try {
                    deleteEmployee(ctype_digit($id) ? (int)$id : 0, $SpringBootBaseURL);
                } catch (Exception $e) {
                    http_response_code(400);
                    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
                    exit();
                }
                break;
            default:
                http_response_code(404);
                echo json_encode(["status: " => "error", "message" => "Service not found"]);
                exit();
        }
        break;
    case 'PATCH': //Patch requests for re activate products or employees
        auth::requireAdmin(true);
        requiresID($id);
        require_once "../controllers/SpringBootController.php";
        switch ($service) {
            case 'product': // Endponit to re activate a product (sets state to available)
                reviveProduct($id, $SpringBootBaseURL);
                break;
            case 'employee': // Endponit to re activate an employee (sets state to alive)
                try {
                    reviveEmployee(ctype_digit($id) ? (int)$id : 0, $SpringBootBaseURL);
                } catch (Exception $e) {
                    http_response_code(400);
                    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
                    exit();
                }
                break;
            default:
                http_response_code(404);
                echo json_encode(["status: " => "error", "message" => "Service not found"]);
                exit();
        }
        break;
    default: // If the request method does not match any known method, return a 405 error
        http_response_code(405);
        echo json_encode(["status" => "error", "message" => "Invalid request method"]);
        exit();
}

/* Validate JSON payload to prevent server errors */
function validateJSON(): bool
{
    if (json_last_error() !== JSON_ERROR_NONE) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Invalid JSON payload"]);
        exit();
    }
    return 1;
}

/* Check if the required ID parameter is provided */
function requiresID($id): bool
{
    if (!isset($id)) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Please provide an ID"]);
        exit();
    }
    return 1;
}
exit();

/* 
============================================================================================================
============================================================================================================
Code made by Francisco Emmanuel Luna Hidalgo Last checked 18/05/2026 
============================================================================================================
============================================================================================================
Instituto Tecnológico de Pachuca, Ingeniería en Sistemas Computacionales, Programación Web, proyecto final
============================================================================================================
============================================================================================================
    @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@%%%%%%%%%%##%%%%%%%%%%@@@@@@@@@@@@@@@@@@@@@@@@@@@
    @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@%%%#*++++++++++++++++++++++++++++*#%%%%%%@@@@@@@@@@@@@@@@@
    @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@%#*+++++++++++++++++++++++++++++++++++++++++++*##%%%@@@@@@@@@@@
    @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@%+++++++++++++++++++++++++++++++++++++++++++++++++++++*#%%@@@@@@@
    @@@@@@@@@@@@@@@@@@@@@@@@@@@@@%%@@@@@#+++++++++++++++++++++++++++++++++++++++++++++++++++++++%@@@@@@@
    @@@@@@@@@@@@@@@@@@@@@@@@@@%%#+#%@@@@%*++++##+++++++++++++++++++++++++++++++++++++++++++++++%%@@@@@@@
    @@@@@@@@@@@@@@@@@@@@@@@%%*+++++%%@@@@%*+++%@@@%#*+++++++++++++++++++++++++++++++++++++++++#%@@@@@@@@
    @@@@@@@@@@@@@@@@@@@@@%#++++++++*%@@@@@%*++%@@@@@@@%#+++++++++++++++++++++++++++++++++++++*%@@@@@@@@@
    @@@@@@@@@@@@@@@@@@@%#++++++++++=#%@@@@@@#+%@@@@@@@@@@%#++++++++++++++++++++++++++++++++++%@@@@@@@@@@
    @@@@@@@@@@@@@@@@@%#++++++++++++++%@@@@@@@%%@@@@@@@@@@@@%%*++++++++++++++++++++++++++++++#%@@@@@@@@@@
    @@@@@@@@@@@@@@@%#++++++++++++++++*%@@@@@@@@@@@@@@@@@@@@@@@%#*++++++++++++++++++++++++++*%@@@@@@@@@@@
    @@@@@@@@@@@@@%%*++++++++++++++++++#%@@@@@@@@@@@@@@@@@@@@@@@@@%#+++++++++++++++++++++++*%@@@@@@@@@@@@
    @@@@@@@@@@@@%#+++++++++++++++++++++%%@@@@@@@@@@@@@@@@@@@@@@@@@@%%*++++++++++++++++++++#%@@@@@@@@@@@@
    @@@@@@@@@@@%*+++++++++++++++++++++++%@@@@@@@@@@@@@@@@@@@@@@@@@@@@%%#+++++++++++++++++#%@@@@@@@@@@@@@
    @@@@@@@@@@%+++++++++++++++++++++++++*%@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@%#++++++++++++++*%@@@@@@@@@@@@@@
    @@@@@@@@%#+++++++++++++++++++++++++++#%@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@%#++++++++++++%@@@@@@@@@@@@@@@
    @@@@@@@%%+++++++++++++++++++++++++++++%@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@%#*++++++++#%@@@@@@@@@@@@@@@
    @@@@@@%%++++++++++++++++++++++++++++++*%@@@@@@@@@@@@@@%%%%%%%%%%%%%%%%@@@@%%##+--*%%@@@@@@@@@@@@@@@@
    @@@@@@%+++++++++++++++++++++++++++++++#%++*#%@@@@%%##*++++++++++++++++*#%%%%=...-=.=%@@@@@@@@@@@@@@@
    @@@@@%*+++++++++++++++++++++++++++++**:-+...-#%#*+++++++++++++++++++++++++##...:*...#@@@@@@@@@@@@@@@
    @@@@%*++++++++++++++++++++++++++++++#-..:+...=%+++++++++++++++++++++++++++*%:..*...:%@@@@@@@@@@@@@@@
    @@@%#+++++++++++++++++++++++++++++++#=...-=..+#++++++++++++++++++++++++++++#%++-..+%@@@@@@@@@@@@@@@@
    @@@%+++++++++++++++++++++++++++++**#%%+:..-**#+++++++++++++++++++++++++++++++*####**#%@@@@@@@@@@@@@@
    @@%#+++++++++++++++++++++++++*#%%@@@%#*#%#%#++++++++++++++++++++++++++++++++++++++++++#%@@@@@@@@@@@@
    @@%++++++++++++++++++++++*#%%@@@@@@%++++++++++++++++++++++++++++++++++++++=+===========*%@@@@@@@@@@@
    @%#+++++++++++++++++++*%%@@@@@@@@%+-=++++++++++++++++++++++++++++++++++++++=:...........:#@@@@@@@@@@
    @%*+++++++++++++++*#%@@@@@@@@@@@%+....-=++++++++++++++++++++=--==++++++++++++=-..........:*%@@@@@@@@
    @%++++++++++++++#%@@@@@@@@@@@@@%+........:=+++++++++++++++++++=.....:-==++++++++=..........#%@@@@@@@
    %#+++++++++++*%@@@@@@@@@@@@@@@%*.............:-===++++++++++++++-.................:-++=:....%@@@@@@@
    %#+++++++++#@@@@@@@@@@@@@@@@@@#:............:-::...::--===+++++++=-....................-*:..-%@@@@@@
    %#+++++=*%@@@@@@@@@@@@@@@@@@@%=..  ......:*=....................................+%@@%+...-:..+@@@@@@
    %#++++++++****#%@@@@@@@@@@@@@#:.     ....+.....:=*#*=:....  .... .....      ..+@@@#.:#@-.....-%@@@@@
    %*+++++++++*#%@@@@@@@@@@@@@@%+.. .   ...::...=@@@@=:-+%*:.                  .*@@@@@+..*@:....:#@@@@@
    %*=+++*##%%@@@@@@@@@@@@@@@@@%=..      ......#@@@@@#....-%+...   .        ...+@@@@@@%..:@#.....*%@@@@
    %%%%%@@@@@@@@@@@@@@@@@@@@@@@%=..      .....#@@@@@@@:.....#*..            ..-@@@@@*:*...*%.....+%@@@@
    @@@@@@@@@@@@@@@@@@@@@@@@@@@@%=..         .-@@@@@@%*=.....:#*.           ...%@#=.:=#*...=@:. ..+%@@@@
    @@@@@@@@@@@@@@@@@@@@@@@@@@@@%=...        .*@@@#-.:*=......:@+...         .++.:*@@@@-...-@:. ..+%@@@@
    @@@@@@@@@@@@@@@@@@@@@@@@@@@@%+...  .     .#%:.:#@@@=...  ..+@:..         .#@@@@@@@%....=@:....+%@@@@
    @@@@@@@@@@@@@@@@@@@@@@@@@@@@@*..         :#+#@@@@@@:...  ...%*..        .-%@@@@@@@=.  .+%.....*@@@@@
    @@@@@@@@@@@@@@@@@@@@@@@@@@@@@#-..        :#@@@@@@@#....  ...=#:.       ..=@@@@@@@#.. ..*+....:#@@@@@
    @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@+.         .#@@@@@@@=.     ....%-.      ...+@@@@@@%..  ..%:....-%@@@@@
    @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@%:.......  .*@@@@@@#:.     ....*=.      ...*@@@@@%......-*.....*@@@@@@
    @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@#.......  .+@@@@@@:..      . .==. .     ..*@@@@+... ...+:....-%@@@@@@
    @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@%#......  .:@@@@@....   .    .-=.     . ..#@@+........:=.....%%@@@@@@
    @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@%*:..... ..#@%+.....       ..:=.       ..=:..:::::::-=:....==--#%@@@
    @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@#:.......-+::---===++==+++++-..........:--:::....... ......:*%@@@@
    @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@%=............................ ...................   ....-%@@@@@@
    @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@%*:......     ..-*+-:....................     .   ....:#%@@@@@@@
    @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@%*:.......  ...:+-:=+*#%%%###***++++..............:+%@@@@@@@@@
    @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@%*:............=#-............:*-.............:*%@@@@@@@@@@@
    @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@%%=............=#*:......:+#-.............-#%@@@@@@@@@@@@@
    @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@%#=:...........=+****+-............:=#%@@@@@@@@@@@@@@@@
    @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@%#+-:......................-+#%@@@@@@@@@@@@@@@@@@@@
    @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@%%%%#*+=-::::::-=+#%%%%@@@@@@@@@@@@@@@@@@@@@@@@
    @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@%+**##%%%@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@
============================================================================================================
============================================================================================================
*/
