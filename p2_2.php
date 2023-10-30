<?php

// Cria uma matriz de 511x511 com todos os elementos iguais a 0
$matriz = array_fill(0, 511, array_fill(0, 511, 0));

$output='p1_2/type3B.png';

function imageCreateFromAny($filepath) {
    $type = exif_imagetype($filepath);
    $allowedTypes = array(
        1,  // [] IMAGETYPE_GIF 
        2,  // [] IMAGETYPE_JPEG 
        3,  // [] IMAGETYPE_PNG 
        6   // [] IMAGETYPE_BMP 
    );
    if (!in_array($type, $allowedTypes)) {
        return false;
    }
    switch ($type) {
        case 1:
            $im = imageCreateFromGif($filepath);
            break;
        case 2:
            $im = imageCreateFromJpeg($filepath);
            break;
        case 3:
            $im = imageCreateFromPng($filepath);
            break;
        case 6:
            $im = imageCreateFromBmp($filepath);
            break;
    }   
    return $im; 
}

function runOnImage($input){
    global $matriz;
    
    // Carregar a imagem
    $imagem = imageCreateFromAny($input);
    
    // Dimensões da imagem
    $largura = imagesx($imagem);
    $altura = imagesy($imagem);
    
    // Itera pelos pixels da imagem em passos de 4 (quadrantes de 4x4 pixels)
    for ($x = 0; $x < $largura - 3; $x += 4) {
        for ($y = 0; $y < $altura - 3; $y += 4) {
    
            $soma_red = 0;
            $soma_green = 0;
    
            // Itera pelos 4x4 pixels do quadrante
            for ($i = 0; $i < 4; $i++) {
                for ($j = 0; $j < 4; $j++) {
                    $rgb = imagecolorat($imagem, $x + $i, $y + $j);
                    $r = ($rgb >> 16) & 0xFF;
                    $g = ($rgb >> 8) & 0xFF;
    
                    $soma_red += $r;
                    $soma_green += $g;
                }
            }
    
            // Calcula a média e multiplica por 2
            $media_red = round(2 * $soma_red / 16);
            $media_green = round(2 * $soma_green / 16);
    
            // Incrementa o contador na matriz
            $matriz[510 - $media_green][$media_red]++;
        }
    }
}

function runOnFolder($pasta) {
    global $matriz;

    // Obtendo todos os arquivos da pasta
    $arquivos = scandir($pasta);

    foreach ($arquivos as $arquivo) {
        if ($arquivo != "." && $arquivo != "..") {  // Ignorando '.' e '..'
            $caminho_completo = $pasta . '/' . $arquivo;
            runOnImage($caminho_completo);
        }
    }
}

runOnFolder('imgsat/00000003B/00000003B');

// Calcula o valor máximo na matriz
$max_valor = max(array_map('max', $matriz));

$soma = 0;
$numeroElementos = 0;

for ($i = 0; $i < 511; $i++) {
    for ($j = 0; $j < 511; $j++) {
        $soma += $matriz[$i][$j];
        $numeroElementos++;
    }
}

$media = $soma / $numeroElementos;

$pow=1/7;
$max_raiz = 4;

$pow = log($max_raiz) / log($max_valor);

echo 'max_valor: '.$max_valor.'<br>';
echo 'média: '.$media.'<br>';
echo 'max_raiz: '.$max_raiz.'<br>';

// Cria uma nova imagem 511x511 para o resultado
$imagem_resultado = imagecreatetruecolor(511, 511);

// Preenche a imagem_resultado de acordo com os valores da matriz
for ($x = 0; $x < 511; $x++) {
    for ($y = 0; $y < 511; $y++) {
        $valor = $matriz[$y][$x];
        $prop=(pow($valor, $pow) / $max_raiz);
        if($prop>1){$prop=1;}
        $intensidade = (int) (255 * $prop);
        $cor = imagecolorallocate($imagem_resultado, 255 - $intensidade, 255 - $intensidade, 255);
        imagesetpixel($imagem_resultado, $x, $y, $cor);
    }
}

// Salvar a imagem resultado
imagepng($imagem_resultado, $output);

// Liberar memória
imagedestroy($imagem);
imagedestroy($imagem_resultado);

?>

<img src='<?php echo  $output.'?t='.time();?>' width='511' height='511' style='border:solid 4px black'>