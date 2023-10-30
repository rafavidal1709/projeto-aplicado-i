import struct
import numpy as np
from PIL import Image
import os
import copy

class PatternFile:
    def __init__(self, file=None, cell=4, line=256, cha=3):
        if file is None:
            self.new(cell, line, cha)
        else:
            self.open(file)

    def add(self, color):
        if len(color) != self.cha:
            raise ValueError(f"Color length must be equal to the number of channels ({self.cha})")

        index = tuple(color)
        self.data[index] += 1
        self.inp += 1

    def new(self, cell=8, line=256, cha=3):
        if cell == 8:
            dtype = np.uint64  # ou np.int64 para inteiros com sinal
        elif cell == 4:
            dtype = np.uint32
        else:
            raise ValueError("Unsupported cell size")

        self.size = line ** cha * cell + 32
        self.cha = cha
        self.line = line
        self.cell = cell
        self.inp = 0
        self.data = np.zeros((line,) * cha, dtype=dtype)

    def open(self, file_name):
        with open(file_name, 'rb') as file:
            header = file.read(7).decode('utf-8')
            zero_byte = struct.unpack('B', file.read(1))[0]

            if header != 'PATTERN' or zero_byte != 0:
                raise ValueError("Invalid file format")

            self.size = struct.unpack('L', file.read(8))[0]
            self.cha = struct.unpack('I', file.read(4))[0]
            self.line = struct.unpack('I', file.read(4))[0]
            self.cell = struct.unpack('I', file.read(4))[0]
            self.inp = struct.unpack('L', file.read(8))[0]

            data_bytes = file.read(np.product((self.line,) * self.cha) * 4)  # Atualize a leitura dos bytes aqui
            self.data = np.frombuffer(data_bytes, dtype=np.uint32).reshape((self.line,) * self.cha)  # Remodelação atualizada

    def save(self, file_name):
        with open(file_name, 'wb') as file:
            file.write(b'PATTERN')
            file.write(struct.pack('B', 0))
            file.write(struct.pack('L', self.size))
            file.write(struct.pack('I', self.cha))
            file.write(struct.pack('I', self.line))
            file.write(struct.pack('I', self.cell))
            file.write(struct.pack('L', self.inp))

            data_bytes = self.data.tobytes()
            file.write(data_bytes)

    def image(self, image_path, channels=(1,1,1)):
        try:
            if image_path.startswith('http'):
                response = requests.get(image_path)
                if response.status_code == 200:
                    img = Image.open(BytesIO(response.content))
                else:
                    print(f"Failed to load the image, status code: {response.status_code}")
                    return
            else:
                img = Image.open(image_path)
            img_data = np.array(img)
            for x in range(img.width):
                for y in range(img.height):
                    pixel = img_data[y, x][:self.cha]  # Pegando apenas os canais necessários
                    pixel = [int(p*c) for p, c in zip(pixel, channels)]  # Aplicando a redução de canais se necessário
                    self.add(pixel)
        except Exception as e:
            print(f"An error occurred while processing the image: {e}")

    def folder(self, folder_path, channels=(1,1,1)):
        try:
            for filename in os.listdir(folder_path):
                if filename.lower().endswith(('.png', '.jpg', '.jpeg', '.bmp', '.tiff')):
                    filepath = os.path.join(folder_path, filename)
                    self.image(filepath, channels)
        except Exception as e:
            print(f"An error occurred while processing the folder: {e}")

    def filter(self, func):
        it = np.nditer(self.data, flags=['multi_index'], op_flags=['readwrite'])
        while not it.finished:
            location = it.multi_index
            old_value = it[0]
            new_value = func(location, old_value)
            delta = new_value - old_value  # Calcula a diferença entre o novo valor e o antigo
            it[0] = new_value
            self.inp += delta  # Atualiza o valor de inp com a diferença
            it.iternext()

    def duplicate(self):
        return copy.deepcopy(self)  # Retorna uma cópia profunda do objeto atual

    def hexToRgb(self, hex_color):
        """Converte uma cor hex para uma tupla RGB."""
        hex_color = hex_color.lstrip('#')
        return tuple(int(hex_color[i:i+2], 16) for i in (0, 2, 4))

    def channels(self, channels):
        return np.sum(self.data[channels, ...], axis=0)

    def to2dImage(self, channels=(0, 1), color='FFFFFF', bg='000000'):
        if self.cha < 2:
            raise ValueError("The data matrix must have at least two channels to be converted to an image.")

        # Converte cores hexadecimais em tuplas RGB
        rgb_color = np.array(self.hexToRgb(color))[np.newaxis, np.newaxis, :]
        rgb_bg = np.array(self.hexToRgb(bg))[np.newaxis, np.newaxis, :]

        # Aqui, precisamos lidar com os canais adequadamente
        data = np.sum(self.data, axis=tuple(range(self.cha))[2:]) if self.cha > 2 else self.data

        max_value = np.max(data)
        if max_value == 0:
            raise ValueError("The maximum value in the selected channels is 0, cannot create an image.")

        # Calcula a interpolação linear entre as cores de fundo e de primeiro plano
        normalized_data = (data / max_value)[:, :, np.newaxis]  # Adicionando uma terceira dimensão aqui
        image_data = (rgb_bg * (1 - normalized_data) + rgb_color * normalized_data).astype(np.uint8)

        img = Image.fromarray(image_data)
        return img

    def __str__(self):
        return f"Size: {self.size}, Channels: {self.cha}, Lines: {self.line}, Cells: {self.cell}, Inputs: {self.inp}, Data Size: {self.data.nbytes if self.data is not None else 0:,}"

# Teste das novas funções
pattern = PatternFile()
print(pattern)  # Exibe os valores iniciais
pattern.add([10, 20, 30])  # Adiciona 1 à célula correspondente à cor (10, 20, 30)
print(pattern)  # Deve exibir os valores reinicializados
image=pattern.to2dImage()
image.show()
