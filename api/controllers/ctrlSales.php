<?php
include '../models/sale.php';
header('Content-Type: application/json');

/* 
    Sales controller, contains functions to handle requests related to sales, 
    such as getting sales information and recording new sales 
*/

/* GET FUNCTIONS */
/* Get all sales, limited to the 10 most recent ones for performance reasons, might change in the future */
function getSales($conn)
{
    $query = 'SELECT * FROM sales ORDER BY s_date DESC LIMIT 10';
    $rows = $conn->query($query);
    $sales = getResult($rows);
    getResponse($sales);
    $conn->close();
}

/* Get sales by employee ID, used for viewing sales history in employee profiles and for admins to see the employee's performance */
function getSalesByEmployeeID($conn, $id)
{
    /* Prepare the SQL statement to fetch sales for the specified employee, we use prepared statements to prevent SQL injection */
    $query = $conn->prepare("SELECT * FROM sales WHERE s_saler = ? ORDER BY s_date DESC");
    if (!$query) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Error with statement"]);
    } else {
        if(!is_numeric($id)){
            http_response_code(400);
            echo json_encode(["status" => "error", "message" => "Invalid employee ID"]);
            exit();
        }
        $query->bind_param("i", $id);
        $query->execute();
        $rows = $query->get_result();
        $sales = getResult($rows);
        getResponse($sales);
    }
    $query->close();
    $conn->close();
}

/*
 * Function to get result set and create sale objects 
 * Helps to keep the get functions cleaner and more focused on handling the request and response
*/
function getResult($rows): array
{
    $sales = [];
    $error = [];
    if ($rows->num_rows > 0) {
        while ($row = $rows->fetch_assoc()) {
            try {
                $s = new sale($row);
                array_push($sales, $s);
            } catch (InvalidArgumentException $e) {
                array_push($error, $e->getMessage());
                return $error;
            }
        }
    }
    return $sales;
}

function getResponse($sales){
    if (empty($sales)) {
        http_response_code(404);
        echo json_encode(["status" => "error", "message" => "No matching records found"]);
    } else {
        echo json_encode($sales);
    }
}


/* POST FUNCTIONS */
/* 
    Function to record a new sale, avoid touching it if you don't know what you're doing
    as this functions handles insetions, updates and stock in multiople tables and records at the same time
    Unproper handling of this function could lead to data inconsistency and loss of information, so be careful when making changes to it
*/
function inSale($conn, $input){
    /* Set the report mode to throw exceptions for better error handling, this way we can catch any errors that occur during the transaction and handle them properly */
    $conn->report_mode = MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT;

    /* Validate the input parameters, we expect a JSON object with a "Products" field that contains an array of products with their code and amount sold */
    if (!isset($input) || !is_array($input)) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Invalid input format, expected a JSON object"]);
        exit();
    }

    /* 
        Validate the presence of the "Products" field and that it is a non-empty array, 
        we also check that each product has a valid code and amount sold, but we will handle that 
        in the database with foreign key constraints and stored procedures, so we just need 
        to make sure that the input is well formed 
    */
    if (!isset($input["Products"]) || !is_array($input["Products"]) || empty($input["Products"])) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Products are required"]);
        exit();
    }

    /* Validate each product in the array, we expect each product to have a "Code" field that is a string and an "Amount" field that is a positive integer */
    foreach ($input["Products"] as $product) {
        if (!isset($product["Code"]) || !is_string($product["Code"])) {
            http_response_code(400);
            echo json_encode(["status" => "error", "message" => "Each product must have a valid code"]);
            exit();
        }
        if (!isset($product["Amount"]) || !is_numeric($product["Amount"]) || $product["Amount"] <= 0) {
            http_response_code(400);
            echo json_encode(["status" => "error", "message" => "Each product must have a valid amount sold"]);
            exit();
        }
    }

    /* 
        If all validations pass, we proceed to record the sale in the database, this involves inserting a new record 
        in the Sales table and then inserting records in the sale_sold_products table for each product sold 
    */

    /* 
        We also need the employee ID of the seller, we will get it from the session, so this function should only be accessible to logged in users, 
        and the session should be properly set up when the user logs in 
    */
    $products = $input["Products"]; // get array of products
    $employeeID = $_SESSION['user_id']; // get id form the session

    /* Start the transaction, this way we can ensure that all operations are completed successfully or none at all, preventing data inconsistency in case of errors */
    $conn->begin_transaction();
    /* 
        Prepare the SQL statements for inserting the sale and the sold products, we use prepared 
        statements to prevent SQL injection and to handle the parameters properly 
    */
    $insertSale = $conn->prepare("CALL insert_Sale(curdate(), ?)");
    /* Bind parameters for the sale insertion, we only need the date and the employee ID, the amount will be calculated in the database automatically */
    $insertSale->bind_param("i", $employeeID);
    /* 
        We will prepare the statement for inserting sold products later, after we have the sale ID, 
        which is generated by the database when we insert the sale record, 
        we can get it using $conn->insert_id after executing the insertSale statement 
    */
    try {
        /* 
            Execute the insertSale statement to record the sale, if this fails, an exception will be thrown 
            and we will catch it in the catch block, where we will roll back the transaction and return an error response 
        */
        $insertSale->execute();
        /* Fetch the id of the sale we've just inserted so we use it for the insertion of the products */
        $getLastID = $conn->query("SELECT LAST_INSERT_ID() AS saleID");
        $row = $getLastID->fetch_assoc();
        $saleID = $row['saleID'];

        if(!$saleID){
            throw new Exception("Unnexpected error while recording sale");
        }

        /* Now that we have the sale ID, we can prepare the statement for inserting sold products, we will execute this statement for each product in the products array, if any of these executions fail, an exception will be thrown and we will catch it in the catch block, where we will roll back the transaction and return an error response */
        $insertProduct = $conn->prepare("INSERT INTO sale_sold_products (sale_id, product_code, p_amountSold) VALUES (?, ?, ?)");
        /* Insert a record for each product recycling the same prepared statement */
        foreach ($products as $product) {
            $insertProduct->bind_param("isi", $saleID, $product['Code'], $product['Amount']);
            $insertProduct->execute();
        }

        /* Commit the transaction if all operations are successful and send a success response */
        $conn->commit();
        echo json_encode(["status" => "success", "message" => "Sale recorded successfully"]);
    } /* Catch any error during the transaction */
    catch (Exception $e) { 
        /* Roll back the transaction if any error occurs so we don't have partial data sent */
        $conn->rollback();
        http_response_code(500);
        echo json_encode(["status" => "error", "message" => "Failed to record sale: " . $e->getMessage()]);
    } finally {
        /* Close the prepared statements and the database connection */
        $insertProduct->close();
        $insertSale->close();
        $conn->close();
    }
}

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
?>