<?php
include '../models/product.php';
header('Content-Type: application/json');

/* 
    Product controller, contains functions to handle requests related to products, 
    such as getting product information, adding new products, updating existing products, and managing stock levels
*/

/* GET FUNCTIONS */
/* Get all products, used for product listing and browsing */
function getProduct($conn)
{
    $query = 'SELECT * FROM vwProduct';
    $rows = $conn->query($query);
    $product = getResult($rows);
    getResponse($product);
    $conn->close();
}

/* Get product by ID, used for product details viewing and editing */   
function getProductbyID($conn, $id)
{
    $query = $conn->prepare("SELECT * FROM vwProduct WHERE Code = ?");
    if (!$query) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Error with statement"]);
    } else {
        $query->bind_param("s", $id);
        $query->execute();
        $rows = $query->get_result();
        $product = getResult($rows);
        getResponse($product);
    }
    $query->close();
    $conn->close();
}

function getProductbyName(mysqli $conn, string $name){
    $query = $conn->prepare("SELECT * FROM vwProduct WHERE Name LIKE ?");
    if(!$query){
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Error with statement"]);
    }
    $name = trim($name);
    $name =  preg_replace('/[^a-zA-Z0-9\s]/u', '', $name);
    $name = "%{$name}%";
    try{
        $query->bind_param("s",$name);
        $query->execute();
        $rows = $query->get_result();
        $product = getResult($rows);
        getResponse($product);
    }catch(Exception $e){ 
        http_response_code(400);
        echo json_encode(["status"=>"error", "error"=>$e->getMessage()]);
    }
}

/* 
    Same set of functions as in Employee controller to keep code clean and maintainable 
*/
function getResult($rows): array
{
    $products = [];
    $error = [];
    if ($rows->num_rows > 0) {
        while ($row = $rows->fetch_assoc()) {
            try {
                $p = new product($row, $row['Code']);
                array_push($products, $p);
            } catch (InvalidArgumentException $e) {
                array_push($error, $e->getMessage());
                return $error;
            }
        }
    }
    return $products;
}

function getResponse($product){
    if (empty($product)) {
        http_response_code(404);
        echo json_encode(["status" => "error", "message" => "No matching records found"]);
    } else {
        $responseExclude = ['category'];
        $product = array_map(function($prod) use ($responseExclude) {
            foreach ($responseExclude as $field) {
                unset($prod->$field);
            }
            return $prod;
        }, $product);
        echo json_encode($product);
    }
}

/* POST FUNCTIONS */
/* 
    Function to insert a new product into the database 
    Takes two parameters: the database connection and the input data 
    $conn comes from the connection file and $input comes from the body of the POST request, 
    which is expected to be a JSON object with the product information
*/
function inProduct($conn, $input){
        $requiredParams = ['Code', 'Name', 'Description', 'Price', 'Amount', 'Category'];
        $error = [];
        foreach ($requiredParams as $param) {
            if (!isset($input[$param])) {
                array_push($error, "Missing parameter to add {$param}");
            }
        }
        if (!empty($error)) {
            http_response_code(400);
            echo json_encode(["status" => "error", "error" => $error]);
            exit();
        }
        /*
            Prepare the SQL statement to insert a new product,
            Instead of using a direct INSERT statement, we use a stored procedure 
            that handles the insertion logic and some extra validations 
        */
        try{
            $p = new product($input, $input['Code']);
        }catch(InvalidArgumentException $e){
            http_response_code(400);
            echo json_encode(["status"=>"error", "message"=>$e->getMessage()]);
            exit;
        }
        $query = $conn->prepare("CALL inProduct(?, ?, ?, ?, ?, ?)");
        if (!$query) {
            http_response_code(400);
            echo json_encode(["status" => "error", "message" => "Error preparing request"]);
            exit;
        }
        $query -> bind_param("sssdii", $p->code, $p->name, $p->description, $p->price, $p->amount, $p->category);
        try{
            $query -> execute();
            http_response_code(200);
            echo json_encode(["Status"=>"success", 
                            "Message"=>"Product added correctly",
                            "Name"=>"{$p->name}",
                            "Description"=>"{$p->description}",
                            "Price"=>"{$p->price}"]);
        }catch(Exception $e){
            http_response_code(500);
            echo json_encode(["status"=>"error", "message"=>"Error performing request: {$e -> getMessage()}"]);
        }
        $query -> close();
}

/* PUT FUNCTIONS */
/* 
    Function to update an existing product in the database 
    Takes three parameters: the database connection, the input data, and the product ID 
    $conn comes from the connection file, $input comes from the body of the PUT request, 
    which is expected to be a JSON object with the new product information, and $id is the code of the product to be updated
*/
function updateProduct($conn, $input, $id){
    $newValues = [];
    /* 
        Check which parameters are being updated and validate them, then prepare the new values for the update query 
        Note that we allow values to be null because we only want to update the fields that are provided 
        But if all parameters are null, we return an error because there is nothing to update
    */
    $expectedParams = ['Name', 'Description', 'Price', 'Category', 'Photo', 'Discontinued'];
    foreach($expectedParams as $param){
        if(array_key_exists($param, $input)){
            array_push($newValues, $param);
        }
    }
    if(empty($newValues)){
        http_response_code(400);
        echo json_encode(["status"=>"error", "error"=>"no valid new parameters to send"]);
        exit();
    }
    try{
        $p = new product($input, $id);
    }catch(InvalidArgumentException $e){
        http_response_code(400);
        echo json_encode(["status"=>"error", "message"=>$e->getMessage()]);
        exit;
     }
        $new_discontinued = (isset($input['Discontinued']) && is_bool($input['Discontinued'])) ? $input['Discontinued'] : null; // This can be true, false, or null if not provided
        $new_state = ($new_discontinued === true) ? 'discontinued' : 'available'; // Set state based on discontinued value, if discontinued is true, state is 'discontinued', if false or null, state is 'available'
        /* Prepare the SQL statement to update the product, using a stored procedure that handles the update logic and validations, plus extra database logic for information integrity */
        $query = $conn -> prepare('CALL updateProduct(?, ?, ?, ?, ?, ?, ?)');
        /* Bind the parameters for the stored procedure */
        $query -> bind_param("ssssiss", $id, $p->name, $p->description, $p->price, $p->amount, $p->photo, $new_state);
        /* Try to execute the update and return a success response, or catch any exceptions and return an error response */
        try{
            $query->execute();
            http_response_code(200);
            echo json_encode(["status"=>"success", "message"=>"product updated correctly"]);
        }catch(Exception $e){
            http_response_code(500);
            echo json_encode(["status"=>"error", "message"=>"{$e -> getMessage()}"]);
        }finally{
           $query->close();
           $conn->close();
       }
}

/* 
    Function to decrease the stock of a product, used when a sale is made 
    Takes three parameters: the database connection, the amount to decrease, and the product ID 
    $conn comes from the connection file, $amount is the quantity to decrease, and $id is the code of the product to be updated
    This function is already used in the sales recording process by the database itself, but it can also be used for manual stock adjustments if needed
*/
function decProduct($conn, $amount, $id){
    /* Validate that the amount is a number and is greater than 0, otherwise return an error response */
    if(!is_numeric($amount) || $amount <= 0){
        http_response_code(400);
        echo json_encode(["status"=>"error", "message"=>"Amount must be a positive number"]);
        exit();
    }
    
    /* Try to execute the stock decrease and return a success response, or catch any exceptions and return an error response */
    try{
        /* Prepare the SQL statement to decrease the product stock, using a stored procedure that handles the logic and validations for stock management */
        $query = $conn -> prepare("CALL decProduct(?, ?)");
        if(!$query){
            http_response_code(400);
            echo json_encode(["status"=>"error", "message"=>"Error preparing request"]);
        }
        $query -> bind_param("si", $id, $amount);
        $query -> execute();
        http_response_code(200);
        echo json_encode(["status"=>"success", "message"=>"Product {$id} has {$amount} less stock"]);
    }catch(Exception $e){
        http_response_code(500);
        echo json_encode(["status"=>"error", "message"=>"{$e -> getMessage()}"]);
    }finally{
           $query->close();
           $conn->close();
    }
}

/* 
    Function to increase the stock of a product, used for restocking or correcting stock levels 
    Takes two parameters: the database connection and the product ID 
    $conn comes from the connection file and $id is the code of the product to be updated
    This function can be used for manual stock adjustments when new inventory is added or to correct stock levels if there were any discrepancies
*/
function incProduct($conn, $id){
    try{
        $query = $conn -> prepare("CALL incProduct(?)");
        if(!$query){
            http_response_code(400);
            echo json_encode(["status"=>"error", "message"=>"Error preparing request"]);
            exit();
        }
        $query -> bind_param("s", $id);
        $query -> execute();
        http_response_code(200);
        echo json_encode(["status"=>"success", "message"=>"Product {$id} has increased its amount by 1"]);
    }catch(Exception $e){
        http_response_code(500);
        echo json_encode(["status"=>"error", "message"=>"{$e -> getMessage()}"]);
    }finally{
           $query->close();
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