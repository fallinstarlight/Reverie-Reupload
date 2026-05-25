<?php
include '../models/employee.php';
header('Content-Type: application/json');
/* 
    Employee controller, contains functions to handle requests related to employees, 
    such as getting employee information, adding new employees, 
    and updating existing employees 
*/

/* GET FUNCTIONS */
/* Get all employees (admin only) */
function getEmployee($conn)
{
    $query = 'SELECT * FROM vwEmployee';
    $rows = $conn->query($query);
    $employee = getResult($rows);
    getResponse($employee);
    $conn->close();
}

/* Get employee by ID, used for profile viewing and editing */
function getEmployeebyID($conn, $id)
{
    $query = $conn->prepare("SELECT * FROM vwEmployee WHERE ID = ?");
    if (!$query) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Error with statement"]);
    } else {
        $query->bind_param("i", $id);
        $query->execute();
        $rows = $query->get_result();
        $employee = getResult($rows);
        getResponse($employee);
    }
    $query->close();
    $conn->close();
}

/* 
    Function to get result set and create employee objects 
    Helps to keep the get functions cleaner and more focused on handling the request and response
*/
function getResult($rows): array
{
    $employees = [];
    $error = [];
    if ($rows->num_rows > 0) {
        while ($row = $rows->fetch_assoc()) {
            try {
                $e = new employee($row, $row['ID']);
                array_push($employees, $e);
            } catch (InvalidArgumentException $e) {
                array_push($error, $e->getMessage());
                return $error;
            }
        }
    }
    return $employees;
}

/* 
    Function to send the response according to the data retrieved 
    Also added for code organization 
*/
function getResponse($employee){
    if (empty($employee)) {
        http_response_code(404);
        echo json_encode(["status" => "error", "message" => "No matching records found"]);
    } else {
        $responseExclude = ['password'];
        $employee = array_map(function($emp) use ($responseExclude) {
            foreach ($responseExclude as $field) {
                unset($emp->$field);
            }
            return $emp;
        }, $employee);
        echo json_encode($employee);
    }
}


/* POST FUNCTIONS */
/* 
    Function to insert a new employee into the database 
    Takes two parameters: the database connection and the input data 
    $conn comes from the connection file and $input comes from the body of the POST request, 
    which is expected to be a JSON object with the employee information
*/
function inEmployee($conn, $input){
    /* First validate the input parameters to ensure they meet the required criteria */
    $requiredParams = ['Name', 'Surname', 'Username', 'Password', 'Shift', 'Phone'];
    $error = [];
    foreach ($requiredParams as $param) {
        if (!isset($input[$param])) {
            array_push($error, "Missing parameter to add {$param}");
        }
    }
    if(!empty($error)){
        http_response_code(400);
        echo json_encode(["status" => "error", "error" => $error]);
        exit();
    }
    try {
        $e = new employee($input, null);
    } catch (Exception $ex) {
        http_response_code(400);
        echo json_encode(["status" => "error", "error" => $ex->getMessage()]);
        exit();
    }
    /* Prepare the SQL statement to insert a new employee */
    $query = $conn->prepare("CALL inEmployee(?, ?, ?, ?, ?, ?, ?)");
    /* Hash the password before storing it in the database for security reasons */
    $passwordHash = password_hash($e->password, PASSWORD_DEFAULT);
    /* Bind the input parameters to the SQL statement, using the hashed password */
    $query->bind_param("sssssss", $e->name, $e->surname, $e->username, $passwordHash, $e->shift, $e->phone, $e->photo);
    /* Try to execute the statement and return a success response, or catch any exceptions and return an error response */
    try {
        $query->execute();
        http_response_code(200);
        echo json_encode([
            "Status" => "success",
            "Message" => "Employee added correctly",
            "Name" => "{$e->name} {$e->surname}",
            "Username" => "{$e->username}",
            "Shift" => "{$e->shift}"
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(["status" => "error", "message" => "Error performing request: {$e->getMessage()}"]);
    } finally {
        $query->close();
        $conn->close();
    }
}

/* PUT FUNCTIONS */
/* 
    Function to update an existing employee in the database 
    Takes three parameters: the database connection, the input data, and the employee ID 
    $conn comes from the connection file, $input comes from the body of the PUT request, 
    which is expected to be a JSON object with the employee information to be updated, and $id is the ID of the employee to be updated 
*/
function updateEmployee($conn, $input, $id){
    $newValues = [];
    /* 
        Check which parameters are being updated and validate them, then prepare the new values for the update query 
        Note that we allow values to be null because we only want to update the fields that are provided 
        But if all parameters are null, we return an error because there is nothing to update
    */
    $expectedParams = ['Name', 'Surname', 'Username', 'Password', 'Shift', 'Phone', 'Photo', 'Admin'];
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
    /* Validate the provided parameters and return an error if any of them are invalid, otherwise proceed with the update */
        try{
            $e = new employee($input, $id);
        }catch(InvalidArgumentException $ex){
            http_response_code(400);
            echo json_encode(["status" => "error", "error" => $ex->getMessage()]);
            exit();
        }
        /* Hash the new password if it is provided, otherwise set it to null to indicate that it should not be updated */
        $new_password = (isset($e->password)) ? password_hash($e->password, PASSWORD_DEFAULT) :  null;
        if(isset($input['Admin'])){
            $admin = ($_SESSION['role'] === 'administrator') ? $input['Admin'] : null;
            switch($admin){
                case 1:
                    $new_role = 'administrator';
                    break;
                case 0:
                    if ($_SESSION['user_id'] == $id) {
                        http_response_code(400);
                        echo json_encode(["status" => "error", "message" => "You cannot revoke your own admin privileges"]);
                        exit();
                    } else {
                        $new_role = 'employee';
                    }
                    break;
                default:
                    $new_role = null;
            }
        }else{
            $new_role = null;
        }
        
        /* Prepare the SQL statement to update the employee, using a stored procedure that handles the update logic and validations, plus extra database logic for information integrity */
        $query = $conn -> prepare('CALL updateEmployee(?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $query -> bind_param("issssssss", $id, $e->name, $e->surname, $e->username, $new_password, $e->shift, $e->phone, $e->photo, $new_role);
        /* Try to execute the update and return a success response, or catch any exceptions and return an error response */
        try{
            $query->execute();
            http_response_code(200);
            echo json_encode(["status"=>"success", "message"=>"employee updated correctly"]);
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