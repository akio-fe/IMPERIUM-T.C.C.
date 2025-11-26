<?php
session_start();

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: login.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: enderecos.php');
    exit();
}

require_once __DIR__ . '/conn.php';

$userEmail = $_SESSION['email'] ?? '';
$userId = null;

if ($userEmail !== '') {
    $stmtUser = $conn->prepare('SELECT UsuId FROM Usuario WHERE UsuEmail = ? LIMIT 1');
    if ($stmtUser) {
        $stmtUser->bind_param('s', $userEmail);
        $stmtUser->execute();
        $stmtUser->bind_result($foundId);
        if ($stmtUser->fetch()) {
            $userId = (int)$foundId;
        }
        $stmtUser->close();
    }
}

if ($userId === null) {
    header('Location: enderecos.php?status=error');
    exit();
}

$addressId = isset($_POST['endereco_id']) ? (int)$_POST['endereco_id'] : 0;
if ($addressId <= 0) {
    header('Location: enderecos.php?status=error');
    exit();
}

$stmtDelete = $conn->prepare('DELETE FROM EnderecoEntrega WHERE EndEntId = ? AND UsuId = ?');
if ($stmtDelete) {
    $stmtDelete->bind_param('ii', $addressId, $userId);
    $stmtDelete->execute();

    if ($stmtDelete->affected_rows > 0) {
        $stmtDelete->close();
        header('Location: enderecos.php?status=deleted');
        exit();
    }

    $stmtDelete->close();
}

header('Location: enderecos.php?status=error');
exit();
