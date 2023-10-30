from PIL import Image, ImageDraw
from IPython.display import display, Image
import os
import math

def image_open(filepath):
    try:
        im = Image.open(filepath)
        return im
    except Exception as e:
        return None

def run_on_image(input_path, matriz, resolution=2):
    image = image_open(input_path)
    if not image:
        return matriz

    width, height = image.size

    for x in range(0, width - 3, 4):
        for y in range(0, height - 3, 4):
            soma_red = 0
            soma_green = 0

            for i in range(4):
                for j in range(4):
                    r, g, b = image.getpixel((x + i, y + j))
                    soma_red += r
                    soma_green += g

            media_red = round(resolution * soma_red / 16)
            media_green = round(resolution * soma_green / 16)
            matriz[resolution*255 - media_green][media_red] += 1

    return matriz

def run_on_folder(folder, matriz, resolution=2):
    for root, dirs, files in os.walk(folder):
        for file in files:
            full_path = os.path.join(root, file)
            matriz = run_on_image(full_path, matriz, resolution)
    return matriz

def type_matriz(folders, resolution=2):
    matriz = [[0 for _ in range(resolution*255+1)] for _ in range(resolution*255+1)]
    for folder in folders:
        matriz = run_on_folder(folder, matriz, resolution)
    return matriz

def matriz_data(matriz, resolution=2):
    max_val = max([max(row) for row in matriz])
    total = sum([sum(row) for row in matriz])
    number_of_elements = (resolution*255+1)**2
    avg = total / number_of_elements
    return {"max_val": max_val, "total": total, "num_elements": number_of_elements, "avg": avg}

def pixel_influence(pos, matriz, resolution=2):
    soma = 0
    for i in range(resolution*255+1):
        for j in range(resolution*255+1):
            if matriz[i][j] > 0:
                distance = math.sqrt((pos[0]-j)**2 + (pos[1]-i)**2)
                soma += matriz[i][j] / (1 + distance)**0.5
    return soma

def generate_map(types, types_data, types_col, output, resolution=2):
    img_result = Image.new("RGB", (resolution*255+1, resolution*255+1))

    for x in range(resolution*255+1):
        for y in range(resolution*255+1):
            influences = [pixel_influence((x, y), t, resolution) / data["total"] for t, data in zip(types, types_data)]
            total_influence = sum(influences)
            probs = [infl / total_influence for infl in influences]
            selected_type = probs.index(max(probs))
            color = types_col[selected_type]
            img_result.putpixel((x, y), tuple(color))

    img_result.save(output)

resolution = 1

typeA = type_matriz(['imgsat/00000001', 'imgsat/00000007'], resolution)
typeB = type_matriz(['imgsat/00000005', 'imgsat/00000006'], resolution)
typeC = type_matriz(['imgsat/00000003B/00000003B'], resolution)

typeA_data = matriz_data(typeA, resolution)
typeB_data = matriz_data(typeB, resolution)
typeC_data = matriz_data(typeC, resolution)

output_path = 'p1_2/filter1.png'

generate_map([typeA, typeB, typeC], [typeA_data, typeB_data, typeC_data], [(0, 255, 0), (127, 255, 0), (255, 255, 0)], output_path, resolution)

display(Image(filename=output_path))