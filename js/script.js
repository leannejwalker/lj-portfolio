document.getElementById('upload').addEventListener('change', handleFileSelect);

function handleFileSelect(event) {
  const file = event.target.files[0];
  const reader = new FileReader();

  reader.onload = function (event) {
    const img = new Image();
    img.onload = function () {
      createGridFromImage(img);
    };
    img.src = event.target.result;
  };

  reader.readAsDataURL(file);
}

function createGridFromImage(image) {
  const canvas = document.getElementById('canvas');
  const ctx = canvas.getContext('2d');

  canvas.width = image.width;
  canvas.height = image.height;
  ctx.drawImage(image, 0, 0, image.width, image.height);

  const gridContainer = document.getElementById('grid');
  gridContainer.innerHTML = '';

  let counter = 1;

  for (let y = 0; y < canvas.height; y += 5) {
    for (let x = 0; x < canvas.width; x += 5) {
      const imageData = ctx.getImageData(x, y, 1, 1).data;
      const hexCode = rgbToHex(imageData[0], imageData[1], imageData[2]);

      const cubeContainer = document.createElement('div');
      const cube = document.createElement('div');
      cube.style.backgroundColor = hexCode;
      cubeContainer.appendChild(cube);

      const number = document.createElement('span');
      number.textContent = counter;
      cubeContainer.appendChild(number);

      gridContainer.appendChild(cubeContainer);

      counter++;
    }
  }
}

function rgbToHex(r, g, b) {
  return `#${componentToHex(r)}${componentToHex(g)}${componentToHex(b)}`;
}

function componentToHex(c) {
  const hex = c.toString(16);
  return hex.length === 1 ? '0' + hex : hex;
}
