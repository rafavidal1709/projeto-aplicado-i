<?php
// Conexão com o banco de dados
$conn = new mysqli("localhost", "nome_do_banco", "senha", "usuário");

// Verificar conexão
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$data = json_decode($_POST['data'], true);
$imageId = intval($_POST['id']);
$x=-1;
$y=0;

foreach ($data as $type) {
    $x++;
    if($x==8){$y++;$x=0;}
    
    if ($type == 0) {
        // Se o valor do tipo for 0 e uma linha com as combinações image, x, e y já existir, deletá-la.
        $deleteStmt = $conn->prepare("DELETE FROM `extract` WHERE image = ? AND x = ? AND y = ?");
        $deleteStmt->bind_param("iii", $imageId, $x, $y);
        $deleteStmt->execute();
        $deleteStmt->close();
        continue;
    }

    // Verificar se já existe uma entrada com image, x e y
    $stmt = $conn->prepare("SELECT * FROM extract WHERE image = ? AND x = ? AND y = ?");
    $stmt->bind_param("iii", $imageId, $x, $y);
    $stmt->execute();

    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        // Se existir, atualize o tipo
        $updateStmt = $conn->prepare("UPDATE extract SET type = ? WHERE image = ? AND x = ? AND y = ?");
        $updateStmt->bind_param("iiii", $type, $imageId, $x, $y);
        $updateStmt->execute();
        $updateStmt->close();
    } else {
        // Caso contrário, insira uma nova linha
        $insertStmt = $conn->prepare("INSERT INTO extract (image, x, y, type) VALUES (?, ?, ?, ?)");
        $insertStmt->bind_param("iiii", $imageId, $x, $y, $type);
        $insertStmt->execute();
        $insertStmt->close();
    }

    $stmt->close();
}

$conn->close();

echo "Dados salvos com sucesso!";
?>
