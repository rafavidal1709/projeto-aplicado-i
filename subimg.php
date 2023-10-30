<!DOCTYPE html>
<?php
if(isset($_GET['id'])){
    $id=intval($_GET['id']);
}
else{
    $id=1;
}
$index=$id-1;
$imgSrc=["'/imgsat/baseimg.jpg'","'/imgsat/baseimg2.png'"];
$latRange=["[-21.6841333, -21.6942806]","[-21.6942806, -21.7044279]"];
$lonRange=["[-44.9044806, -44.8846528]","[-44.9044806, -44.8846528]"];
?>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Canvas Image Tool</title>
    <style>
        #overlay {
            display: none;
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(0, 0, 0, 0.7);
            z-index: 9999;
            text-align: center;
            line-height: 100vh;
        }
    </style>
</head>
<body>
    <canvas id="canvas"></canvas>
    <div id="overlay">Salvando...</div>
    <script>
        let imgSrc=<?php echo $imgSrc[$index];?>;
        let latRange = <?php echo $latRange[$index];?>;
        let lonRange = <?php echo $lonRange[$index];?>;
    </script>
    <script src="savesubimg.js"></script>
</body>
</html>
