<?php
// Conexão com o banco de dados
$servername = "localhost";
$username = "nome_do_banco";
$password = "senha";
$dbname = "usuário";

// Criando a conexão
$conn = new mysqli($servername, $username, $password, $dbname);

// Verificando a conexão
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

if (isset($_POST['data'])) {
    $data = $_POST['data'];
    
    // Obter o próximo ID da tabela
    $result = $conn->query("SELECT AUTO_INCREMENT FROM information_schema.TABLES WHERE TABLE_SCHEMA = '{$dbname}' AND TABLE_NAME = 'image'");
    $row = $result->fetch_assoc();
    $nextId = str_pad($row['AUTO_INCREMENT'], 8, '0', STR_PAD_LEFT);

    $file = 'subimg' . $nextId . '.jpg';
    $data = str_replace('data:image/jpeg;base64,', '', $data);
    $data = str_replace('data:image/png;base64,', '', $data);
    $data = str_replace(' ', '+', $data);
    $decodedData = base64_decode($data);

    $path = 'imgsat/' . $file;

    if (file_put_contents($path, $decodedData)) {
        $latMin = $_POST['latMin'];
        $lonMin = $_POST['lonMin'];
        $latMax = $_POST['latMax'];
        $lonMax = $_POST['lonMax'];

        $polygon = "POLYGON(($latMin $lonMin, $latMin $lonMax, $latMax $lonMax, $latMax $lonMin, $latMin $lonMin))";

        // Inserir os dados no banco de dados
        $stmt = $conn->prepare("INSERT INTO image (file, pos) VALUES (?, GeomFromText(?))");
        $stmt->bind_param("ss", $file, $polygon);

        if ($stmt->execute()) {
            echo json_encode(['success' => 1, 'file' => $path]);
        } else {
            echo json_encode(['success' => 0, 'error' => $stmt->error]);
        }

        $stmt->close();
    } else {
        echo json_encode(['success' => 0]);
    }
} else {
    echo json_encode(['success' => 0]);
}

$conn->close();
?>
