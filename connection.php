<?php

// variables
$servername = "localhost";
$username = "root";
$password = "";


try {
    $conn = new PDO("mysql:host=$servername;dbname=codewars", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "SUCCESS! <br />";
    // try to connect (about to change soon) AND EXPLAIN MORE

    // might put the sql into $sql??
    $stmt = $conn->prepare("SELECT * FROM shortener");
    $stmt->execute();

    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    // get all info and display it (might be better with prepare (the correct way :) ))

    // put on pre(s) to make it prettier
    echo "<pre>";
    print_r($results); // not echo cause string conversion ahhhhhh
    echo "</pre>";

} catch (PDOException $e) {
    die("NO: " . $e->getMessage());
    // if something happens, let me know ;)
}

