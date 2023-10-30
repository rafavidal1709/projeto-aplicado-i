from PIL import Image
import numpy as np

# Abre a imagem
imagem = Image.open("path_da_sua_imagem.jpg")

# Converte para RGB (caso não seja)
imagem = imagem.convert("RGB")

# Cria uma matriz de 511x511 com todos os elementos iguais a 0
matriz = np.zeros((511, 511), dtype=int)

# Dimensões da imagem
largura, altura = imagem.size

# Itera pelos pixels da imagem em passos de 4 (quadrantes de 4x4 pixels)
for x in range(0, largura-3, 4):
    for y in range(0, altura-3, 4):

        soma_red = 0
        soma_green = 0

        # Itera pelos 4x4 pixels do quadrante
        for i in range(4):
            for j in range(4):
                r, g, b = imagem.getpixel((x+i, y+j))
                soma_red += r
                soma_green += g

        # Calcula a média e multiplica por 2
        media_red = round(2 * soma_red / 16)
        media_green = round(2 * soma_green / 16)

        # Incrementa o contador na matriz
        matriz[510 - media_green, media_red] += 1

# Calcula o valor máximo na matriz
max_valor = matriz.max()

# Cria uma nova imagem 511x511 para o resultado
imagem_resultado = Image.new("RGB", (511, 511))

# Preenche a imagem_resultado de acordo com os valores da matriz
for x in range(511):
    for y in range(511):
        valor = matriz[y, x]
        intensidade = int(255 * (valor / max_valor))
        cor = (255 - intensidade, 255 - intensidade, 255)
        imagem_resultado.putpixel((x, y), cor)

# Salva a imagem resultado
imagem_resultado.save("resultado.png")
