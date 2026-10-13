(() => {
  const supportedTypes = new Set(['image/jpeg', 'image/png', 'image/webp']);
  const targetBytes = 1.5 * 1024 * 1024;
  const maxDimension = 2000;

  const loadImage = file => new Promise((resolve, reject) => {
    const image = new Image();
    const url = URL.createObjectURL(file);
    image.onload = () => {
      URL.revokeObjectURL(url);
      resolve(image);
    };
    image.onerror = () => {
      URL.revokeObjectURL(url);
      reject(new Error('Foto tidak dapat dibaca. Pilih foto JPG, PNG, atau WebP.'));
    };
    image.src = url;
  });

  const canvasBlob = (canvas, quality) => new Promise(resolve => {
    canvas.toBlob(resolve, 'image/jpeg', quality);
  });

  window.prepareIdentityImage = async input => {
    const original = input.files?.[0];
    if (!original) return null;
    const extension = original.name.split('.').pop()?.toLowerCase();
    const supportedExtension = ['jpg', 'jpeg', 'png', 'webp'].includes(extension);
    if ((original.type && !supportedTypes.has(original.type)) || (!original.type && !supportedExtension)) {
      throw new Error('Gunakan foto JPG, PNG, atau WebP.');
    }
    if (original.size <= targetBytes) return original;

    const image = await loadImage(original);
    const scale = Math.min(1, maxDimension / Math.max(image.naturalWidth, image.naturalHeight));
    const canvas = document.createElement('canvas');
    canvas.width = Math.max(1, Math.round(image.naturalWidth * scale));
    canvas.height = Math.max(1, Math.round(image.naturalHeight * scale));
    const context = canvas.getContext('2d', { alpha: false });
    context.fillStyle = '#ffffff';
    context.fillRect(0, 0, canvas.width, canvas.height);
    context.drawImage(image, 0, 0, canvas.width, canvas.height);

    let optimized = null;
    for (const quality of [0.82, 0.72, 0.62]) {
      optimized = await canvasBlob(canvas, quality);
      if (optimized && optimized.size <= targetBytes) break;
    }
    if (!optimized || optimized.size >= original.size) return original;

    const baseName = original.name.replace(/\.[^.]+$/, '') || 'dokumen-identitas';
    const file = new File([optimized], `${baseName}.jpg`, {
      type: 'image/jpeg',
      lastModified: Date.now(),
    });
    try {
      const transfer = new DataTransfer();
      transfer.items.add(file);
      input.files = transfer.files;
    } catch {
      return original;
    }

    return file;
  };
})();
