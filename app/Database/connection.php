<?php
$servername = "localhost";
$username = "root";
$password = "";
$database = "imperium";

$conn = new mysqli($servername, $username, $password, $database);

if ($conn->connect_error) {
    die("Conexão falhou: " . $conn->connect_error);
}

// Função para exibir mensagens de sucesso ou erro
function showMessage($success, $message) {
    $status = $success ? "success" : "danger";
    echo "<div class='alert alert-$status' role='alert'>$message</div>";
}
?>