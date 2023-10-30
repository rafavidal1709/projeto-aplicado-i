<?php
// Conexão com o banco de dados
$conn = new mysqli("localhost", "nome_do_banco", "senha", "usuário");

// Verificar conexão
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

if (isset($_GET['id'])) {
    $imageId = intval($_GET['id']); 

    // Crie uma matriz 8x8 preenchida com 0s
    $phpMatrix = array_fill(0, 8, array_fill(0, 8, 0));

    // Selecionar os dados relevantes da tabela
    $stmt = $conn->prepare("SELECT x, y, type FROM extract WHERE image = ?");
    $stmt->bind_param("i", $imageId);
    $stmt->execute();

    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $x = $row['x'];
        $y = $row['y'];
        $type = $row['type'];
        $phpMatrix[$y][$x] = $type; // Coloque o tipo na posição correspondente
    }
    
    $stmt->close();
} else {
    die("ID de imagem não fornecido, carregando id=1... <script>window.location.href = 'mark.php?id=1';</script>");
}

$result = $conn->query("SELECT MAX(id) as maxId FROM image");
$maxId = $result->fetch_assoc()['maxId'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Grade Interativa</title>
    <style>
        html, body {
            margin: 0;
            padding: 0;
            width: 100%;
            height: 100%;
            overflow: hidden;
            display: flex;
            justify-content: center;
            align-items: center;
            background-color: #000;
        }

        canvas {
            display: block;
            max-width: 90%; 
            max-height: 90%;
            margin: auto;
        }
        
        #colorPalette {
            display: block;
            margin-left: 5%;
            margin-right: 5%;
            vertical-align: top;
            background-color: gray;
            padding:16px;
        }
        
        #menu {
            position: absolute;
            top: 5%;
            left: 5%;
            padding: 8px;
            background-color: transparent;
            border: none;
            border-radius: 8px;
            display: flex;
            flex-direction: column;
            align-items: center;
            color: white;
            font-weight: bold;
        }
    
        #menu > * {
            margin: 4pt 0;
        }
        
        #menu div {
            display: flex;
            align-items: center;
        }
    </style>
</head>
<body>
    <div id="overlay" style="display: none; position: absolute; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(0, 0, 0, 0.5); z-index: 1000; justify-content: center; align-items: center;">
        <span id="overlayText" style="color: white; font-weight: bold;">Aguarde, salvando...</span>
    </div>
    <canvas id="canvas"></canvas>
    <canvas id="colorPalette"></canvas>
    <div id="menu">
        <button id="saveBtn" style="background-color: green; color: white; border: none; border-radius: 8px; cursor:pointer" onmouseover="this.style.backgroundColor='greenyellow'" onmouseout="this.style.backgroundColor='green'">Salvar</button>
        <div id="imageInfo"><?= $_GET['id'] ?> / <?= $maxId ?></div>
        <div>
            <button id="prevBtn" onclick="navigateImage(-1)" <?= $_GET['id'] == 1 ? 'disabled' : '' ?> onmouseover="this.style.backgroundColor='lightgray'" onmouseout="this.style.backgroundColor='darkgray'">&#8592;</button>
            <button id="nextBtn" onclick="navigateImage(1)" <?= $_GET['id'] == $maxId ? 'disabled' : '' ?> onmouseover="this.style.backgroundColor='lightgray'" onmouseout="this.style.backgroundColor='darkgray'">&#8594;</button>
        </div>
        <button id="resetBtn" onclick="location.reload();" style="background-color: darkgray; color: white; border: none; border-radius: 8px; cursor:pointer" onmouseover="this.style.backgroundColor='lightgray'" onmouseout="this.style.backgroundColor='darkgray'">Anular</button>
        <button id="paintAllBtn" style="background-color: gray; color: white; border: solid 4px transparent; border-radius: 16px; cursor:pointer" onclick="togglePaintAll()" onmouseover="this.style.backgroundColor='lightgray'" onmouseout="this.style.backgroundColor='darkgray'">Pintar tudo</button>
        <div>
            <?php
            $result = $conn->query("SELECT id, des FROM type");
            
            while ($type = $result->fetch_assoc()) {
                // Contar as referências para cada type na tabela extract
                $countResult = $conn->query("SELECT COUNT(*) as count FROM extract WHERE type = " . $type['id']);
                $count = $countResult->fetch_assoc()['count'];
            
                // Mostrar o resultado
                echo $type['des'] . ": " . $count . "<br>";
            }
            ?>
        </div>
    </div>
    <script>
        let canvas = document.getElementById('canvas');
        let ctx = canvas.getContext('2d');
        
        let paletteCanvas = document.getElementById('colorPalette');
        let paletteCtx = paletteCanvas.getContext('2d');
        
        const COLORS = ['#000000',  '#A020F0',      '#DEB887',              '#FFFF00',  '#FF0000',      '#FF7500',      '#00FF00',              '#0000FF'];
        const LABELS = ['[Apagar]', 'Construções',  'Estradas e platores',  'Pastagem', 'Plantação',    'Eucalípto',    'Área de preservação',  'Água'];
        const TYPES = [ 0,          4,              3,                      5,          6,              7,              1,                      2];
        let PAINT = null; // Variável global para armazenar o índice da cor selecionada
        let PAINT_ALL = false;
        
        const GRID_SIZE = 8;
        let gridMatrix = <?php echo json_encode($phpMatrix); ?>;
        
        for (let i = 0; i < gridMatrix.length; i++) {
            for (let j = 0; j < gridMatrix[i].length; j++) {
                let typeValue = gridMatrix[i][j]; 
                let index = TYPES.indexOf(typeValue);
                if (index !== -1) {
                    gridMatrix[i][j] = index;
                }
            }
        }
        
        let maxLabelWidth = Math.max(...LABELS.map(label => paletteCtx.measureText(label).width));
        let paletteWidth = 64 + 8 + maxLabelWidth;
        paletteCanvas.width = paletteWidth;

        let squareWidth;
        let squareHeight;
        let drawWidth;
        let drawHeight;
        
        canvas.addEventListener('mousemove', highlightSquare);
        canvas.addEventListener('mouseout', clearHighlight);
        
        function highlightSquare(e) {
            // Redesenhe a imagem
            ctx.drawImage(img, 0, 0, drawWidth, drawHeight);
        
            // Coordenadas relativas ao canvas
            let x = e.clientX - canvas.getBoundingClientRect().left;
            let y = e.clientY - canvas.getBoundingClientRect().top;
        
            // Identifique qual quadrado o mouse está
            let col = Math.floor(x / squareWidth);
            let row = Math.floor(y / squareHeight);
        
            // Desenhe um retângulo semi-transparente sobre este quadrado
            ctx.fillStyle = 'rgba(255,255,255,0.2)';
            ctx.fillRect(col * squareWidth, row * squareHeight, squareWidth, squareHeight);
        
            // Desenhe a grade
            drawGrid();
            
            drawPaintedSquares()
        }
        
        function drawGrid() {
            for (let i = 0; i <= drawWidth; i += squareWidth) {
                // linhas verticais
                ctx.strokeStyle = 'black';
                ctx.beginPath();
                ctx.moveTo(i, 0);
                ctx.lineTo(i, drawHeight);
                ctx.stroke();
            }
            for (let i = 0; i <= drawHeight; i += squareHeight) {
                // linhas horizontais
                ctx.beginPath();
                ctx.moveTo(0, i);
                ctx.lineTo(drawWidth, i);
                ctx.stroke();
            }
        }
        
        function drawPaintedSquares() {
            for (let row = 0; row < GRID_SIZE; row++) {
                for (let col = 0; col < GRID_SIZE; col++) {
                    if (gridMatrix[row][col] !== 0) {
                        ctx.fillStyle = `rgba(${hexToRgb(COLORS[gridMatrix[row][col]])}, 0.3)`;
                        ctx.fillRect(col * (canvas.width / GRID_SIZE), row * (canvas.height / GRID_SIZE), canvas.width / GRID_SIZE, canvas.height / GRID_SIZE);
                    }
                }
            }
        }

        function clearHighlight() {
            // Redesenhe a imagem
            ctx.drawImage(img, 0, 0, drawWidth, drawHeight);
        
            // Desenhe a grade sobre a imagem
            drawGrid();
            drawPaintedSquares();
        }
        
        canvas.addEventListener('click', function(e) {
            if (PAINT !== null && PAINT_ALL) {
                gridMatrix = Array.from({ length: GRID_SIZE }, () => Array(GRID_SIZE).fill(PAINT));
                clearHighlight();
            }
            if (PAINT !== null) {
                let x = e.clientX - canvas.getBoundingClientRect().left;
                let y = e.clientY - canvas.getBoundingClientRect().top;
                
                let col = Math.floor(x / (canvas.width / GRID_SIZE));
                let row = Math.floor(y / (canvas.height / GRID_SIZE));
        
                gridMatrix[row][col] = PAINT;
        
                // Pintar o quadrado com a cor selecionada
                ctx.fillStyle = `rgba(${hexToRgb(COLORS[PAINT])}, 0.3)`;
                ctx.fillRect(col * (canvas.width / GRID_SIZE), row * (canvas.height / GRID_SIZE), canvas.width / GRID_SIZE, canvas.height / GRID_SIZE);
            }
        });
        
        // Função para converter hexadecimal para RGB
        function hexToRgb(hex) {
            let bigint = parseInt(hex.substring(1), 16);
            let r = (bigint >> 16) & 255;
            let g = (bigint >> 8) & 255;
            let b = bigint & 255;
        
            return `${r}, ${g}, ${b}`;
        }


        function drawColorPalette() {
            paletteCtx.fillStyle = 'gray';
            paletteCtx.fillRect(0, 0, paletteWidth, paletteCanvas.height);
        
            for (let i = 0; i < COLORS.length; i++) {
                paletteCtx.fillStyle = COLORS[i];
                paletteCtx.fillRect(0, i * (64 + 8), 64, 64);
        
                paletteCtx.fillStyle = '#000';  // Cor preta para a legenda
                paletteCtx.fillText(LABELS[i], 72, (i * (64 + 8)) + 32 + 4);
        
                // Desenhe o contorno se o índice i for igual a PAINT
                if (i === PAINT) {
                    paletteCtx.strokeStyle = 'white';
                    paletteCtx.lineWidth = 8;
                    paletteCtx.strokeRect(4, i * (64 + 8) + 4, 56, 56);
                }
            }
        }
        
        paletteCanvas.addEventListener('mousemove', function(e) {
            drawColorPalette(); // Redesenhe a palheta para limpar quaisquer destaques anteriores
        
            let y = e.clientY - paletteCanvas.getBoundingClientRect().top;
            let index = Math.floor(y / (64 + 8));
        
            if (index < COLORS.length) {
                paletteCtx.fillStyle = 'rgba(255,255,255,0.2)';
                paletteCtx.fillRect(0, index * (64 + 8), paletteWidth, 64);
            }
        });
        
        paletteCanvas.addEventListener('click', function(e) {
            let y = e.clientY - paletteCanvas.getBoundingClientRect().top;
            let index = Math.floor(y / (64 + 8));
        
            if (index < COLORS.length) {
                PAINT = index;
                drawColorPalette(); // Redesenhe a palheta para mostrar o contorno selecionado
            }
        });
        
        paletteCanvas.addEventListener('mouseout', drawColorPalette);
        
        document.getElementById('saveBtn').addEventListener('click', function() {
            // Mostra a sobre-camada
            document.getElementById('overlay').style.display = 'flex';
        
            // Converte a matriz 8x8 em uma lista
            let flatArray = [];
            for(let y = 0; y < 8; y++) {
                for(let x = 0; x < 8; x++) {
                    flatArray.push(gridMatrix[y][x] === -1 ? 0 : TYPES[gridMatrix[y][x]]);
                }
            }
        
            // Prepara os dados para serem enviados
            let formData = new FormData();
            formData.append('data', JSON.stringify(flatArray));
            formData.append('id', <?php echo $_GET['id']; ?>); // supondo que a variável $id já esteja disponível
        
            fetch('savetexture.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.text())
            .then(data => {
                // Altera a mensagem na sobre-camada para a resposta recebida
                document.getElementById('overlayText').textContent = data;
        
                // Aguarda 5 segundos e esconde a sobre-camada
                setTimeout(function() {
                    document.getElementById('overlay').style.display = 'none';
                }, 3000);
            })
            .catch(error => {
                console.error('Error:', error);
                document.getElementById('overlayText').textContent = 'Erro ao salvar!';
                setTimeout(function() {
                    document.getElementById('overlay').style.display = 'none';
                }, 5000);
            });
        });
        
        function navigateImage(direction) {
            let newId = <?= $_GET['id'] ?> + direction;
            location.href = "mark.php?id=" + newId;
        }

        function togglePaintAll() {
            PAINT_ALL = !PAINT_ALL;
            let paintAllButton = document.getElementById('paintAllBtn');
            if (PAINT_ALL) {
                paintAllButton.style.borderColor = "red";
                paintAllButton.style.color = "red";
            } else {
                paintAllButton.style.borderColor = "transparent";
                paintAllButton.style.color = "white";
            }
        }

        let img = new Image();
        img.onload = function() {
            let maxWidth = window.innerWidth * 0.9; // Considerando margens de 5% de cada lado
            let maxHeight = window.innerHeight * 0.9; // Considerando margens de 5% de cada lado

            let scale = Math.min(maxWidth / img.width, maxHeight / img.height);
            drawWidth = img.width * scale;
            drawHeight = img.height * scale;

            canvas.width = drawWidth;
            canvas.height = drawHeight;
            
            squareWidth = drawWidth / (img.width / 60);
            squareHeight = drawHeight / (img.height / 60);

            ctx.drawImage(img, 0, 0, drawWidth, drawHeight);
            drawGrid();
            drawPaintedSquares();
            
            paletteCanvas.width = 64 + 8 + Math.max(...LABELS.map(label => paletteCtx.measureText(label).width));
            paletteCanvas.height = canvas.height;
        
            drawColorPalette();
        };

        <?php
            if (isset($_GET['id'])) {
                $id = intval($_GET['id']); // Sanitizar o valor para ter certeza de que é um número

                $stmt = $conn->prepare("SELECT file FROM image WHERE id = ?");
                $stmt->bind_param("i", $id);
                $stmt->execute();

                $result = $stmt->get_result();
                if ($row = $result->fetch_assoc()) {
                    $file = $row['file'];
                    echo 'img.src = "imgsat/' . $file . '";';
                } else {
                    echo 'alert("Imagem não encontrada.");';
                }

                $stmt->close();
            }
            $conn->close();
        ?>
    </script>
</body>
</html>
