let canvas = document.getElementById("canvas");
let ctx = canvas.getContext("2d");
let overlay = document.getElementById("overlay");
let image = new Image();
image.src = imgSrc;

let scale = 1;
let offsetX = 0;
let offsetY = 0;
let rect = { x: 0, y: 0, width: 480, height: 480 };
let click = { x: 0, y: 0 };
let has_rect = false;
let isDragging = false;
let mouseX, mouseY;

canvas.width = window.innerWidth;
canvas.height = window.innerHeight;

let originalWidth = image.width;
let originalHeight = image.height;

function coordsFromPixel(x, y) {
    let lat = latRange[0] + (y / originalHeight) * (latRange[1] - latRange[0]);
    let lon = lonRange[0] + (x / originalWidth) * (lonRange[1] - lonRange[0]);
    return { lat, lon };
}

image.onload = function() {
    draw();
}

canvas.addEventListener("mousedown", function(e) {
    let rectStartX = rect.x * scale + offsetX;
    let rectStartY = rect.y * scale + offsetY;

    if (e.clientX > rectStartX && e.clientX < rectStartX + rect.width * scale &&
        e.clientY > rectStartY && e.clientY < rectStartY + rect.height * scale) {
        isDragging = true;
        mouseX = e.clientX;
        mouseY = e.clientY;
    }
});

canvas.addEventListener("mouseup", () => isDragging = false);

canvas.addEventListener("mousemove", function(e) {
    if (isDragging) {
        let dx = e.clientX - mouseX;
        let dy = e.clientY - mouseY;

        offsetX += dx;
        offsetY += dy;

        mouseX = e.clientX;
        mouseY = e.clientY;

        draw();
    }
});

canvas.addEventListener("wheel", function(e) {
    let zoom = e.deltaY < 0 ? 1.1 : 0.9;
    scale *= zoom;

    draw();
});

canvas.addEventListener("click", function(e) {
    rect.x = (e.clientX - offsetX) / scale - rect.width / 2;
    rect.y = (e.clientY - offsetY) / scale - rect.height / 2;
    click.x = (e.clientX - offsetX) / scale;
    click.y = (e.clientY - offsetY) / scale;
    has_rect = true;
    draw();
});

let originalImage = new Image();
originalImage.src = imgSrc;

function extractSubImageFromOriginal(x, y, width, height) {
    let tempCanvas = document.createElement('canvas');
    tempCanvas.width = width;
    tempCanvas.height = height;
    let tempCtx = tempCanvas.getContext('2d');

    tempCtx.drawImage(originalImage,
        x, y, width, height, // Coordenadas e dimensões da sub-imagem
        0, 0, width, height  // Coordenadas e dimensões no canvas temporário
    );

    return tempCanvas.toDataURL("image/jpeg"); // Converter o canvas temporário para string base64 e retornar
}

document.addEventListener("keydown", function(e) {
    if (e.key === "Enter") {
        overlay.style.display = "block";

        let topLeft = coordsFromPixel(
            click.x-rect.width/2,
            click.y-rect.width/2
        );
        let bottomRight = coordsFromPixel(
            click.x+rect.width/2,
            click.y+rect.width/2
        );

        let imgData = extractSubImageFromOriginal(rect.x, rect.y, 480, 480);
        
        let fileName = "subimage";

        let formData = new FormData();
        formData.append("file", fileName);
        formData.append("data", imgData);
        formData.append("latMin", bottomRight.lat.toString());
        formData.append("lonMin", topLeft.lon.toString());
        formData.append("latMax", topLeft.lat.toString());
        formData.append("lonMax", bottomRight.lon.toString());

        fetch('savesubimg.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.text())
        .then(data => {
            if (data !== '0') {
                overlay.textContent = "Imagem salva com sucesso: " + data;
            } else {
                overlay.textContent = "Erro ao salvar";
            }
            setTimeout(() => {
                overlay.style.display = "none";
                overlay.textContent = "Salvando...";
            }, 5000);
        });
    }
});

function draw() {
    ctx.clearRect(0, 0, canvas.width, canvas.height);

    ctx.drawImage(image, offsetX, offsetY, image.width * scale, image.height * scale);
    ctx.strokeStyle = "red";
    if( has_rect ){
        ctx.strokeRect(rect.x * scale + offsetX, rect.y * scale + offsetY, rect.width * scale, rect.height * scale);
    }
}

