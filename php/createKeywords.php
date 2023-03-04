<?php
/*
    This function needed to create keywords from a string, which is target for search. 
    Having keywords column in DB table makes search more efficient, because:
    -Saves time for processing the target string during the search.
    -Creates the possibility of adding new keywords by which the target string can be found without changing it.
    -Creates the possibility of excluding exception words (or service words).

    !!! Before you use the code make sure that you have created 'keywords' column in target table.
    
    In this example I have created keywords for the table 'cities'. 
*/
$conn = mysqli_connect("host", "login", "password", "database");
mysqli_query($conn, "SET NAMES 'utf8' COLLATE 'utf8_general_ci'");
mysqli_query($conn, "SET CHARACTER SET 'utf8'");

// This process can take for a while if you have a lot of items in the table. You can increase the time if necessary.
ini_set('max_execution_time', 1000);

// words that do not carry a semantic load for search (use optionally)
$servWords = array("and", "or", "of", "a", "the", "some", "any");
$errors = [];

$sql = "SELECT `city`, `id` FROM `cities`";
$res = mysqli_query($conn, $sql);


while ($row = mysqli_fetch_assoc($res)) {
    $textArr = [];
    $keysArr = [];

    $id = $row['id'];
    $name = preg_replace("/[^\w\s]/iu", "", $row['city']);
    $textArr = preg_split("/[\W]/iu", mb_strtolower($name), null, PREG_SPLIT_NO_EMPTY);

    foreach ($textArr as $aKey => $a) {
        if (!in_array($a, $servWords)) {
            $keysArr[] = $a;
        }
        // OR (if you don't want to use exceptions)
        // $keysArr[] = $a;
    }

    $keys = implode("+", $keysArr);
    $keys = '+' . $keys;

    $sql2 = "UPDATE `cities` SET `keywords`='$keys' WHERE `id`='$id'";
    $res2 = mysqli_query($conn, $sql2);

    if ($res2 == false) {
        $errors[] = 'error with city ' . $row['city'] . '-' . $row['id'];
    }
}

if (empty($errors)) {
    echo '1';
} else {
    echo json_encode($errors);
}

mysqli_close($conn);