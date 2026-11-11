/**
 * Balochistan Minerals - Client-Side Browser WebP Compression & Vault Pre-Processor
 * Resizes large camera images to max 1600px WebP + 400px thumbnail strictly before upload,
 * saving ~90-97% bandwidth & storage without requiring server PHP GD/Python runtimes.
 */
window.BMVaultCompressor = (function () {
  const MAX_EDGE = 1600;
  const THUMB_EDGE = 400;
  const MAIN_QUALITY = 0.78;
  const THUMB_QUALITY = 0.72;

  function formatBytes(bytes) {
    const b = Math.max(0, Number(bytes) || 0);
    if (b < 1024) return b + ' B';
    if (b < 1048576) return Math.round(b / 1024) + ' KB';
    return (b / 1048576).toFixed(2) + ' MB';
  }

  function loadImageSource(file) {
    if (typeof createImageBitmap === 'function') {
      return createImageBitmap(file).then(bitmap => ({
        width: bitmap.width,
        height: bitmap.height,
        draw: (ctx, w, h) => ctx.drawImage(bitmap, 0, 0, w, h),
        close: () => { try { bitmap.close(); } catch (e) {} }
      }));
    }
    return new Promise((resolve, reject) => {
      const reader = new FileReader();
      reader.onload = () => {
        const img = new Image();
        img.onload = () => resolve({
          width: img.naturalWidth || img.width,
          height: img.naturalHeight || img.height,
          draw: (ctx, w, h) => ctx.drawImage(img, 0, 0, w, h),
          close: () => {}
        });
        img.onerror = reject;
        img.src = reader.result;
      };
      reader.onerror = reject;
      reader.readAsDataURL(file);
    });
  }

  function renderScaledCanvas(imgSrc, maxEdge) {
    let targetW = imgSrc.width;
    let targetH = imgSrc.height;
    if (targetW > maxEdge || targetH > maxEdge) {
      if (targetW >= targetH) {
        targetH = Math.max(1, Math.round(targetH * (maxEdge / targetW)));
        targetW = maxEdge;
      } else {
        targetW = Math.max(1, Math.round(targetW * (maxEdge / targetH)));
        targetH = maxEdge;
      }
    }
    const canvas = document.createElement('canvas');
    canvas.width = targetW;
    canvas.height = targetH;
    const ctx = canvas.getContext('2d');
    ctx.drawImage ? imgSrc.draw(ctx, targetW, targetH) : null;
    return canvas;
  }

  function canvasToBlobWithMime(canvas, mime, quality) {
    return new Promise(resolve => {
      try {
        canvas.toBlob(blob => resolve(blob || null), mime, quality);
      } catch (e) {
        resolve(null);
      }
    });
  }

  async function compressFile(file) {
    const origBytes = file.size || 0;
    const ext = (file.name.split('.').pop() || '').toLowerCase();
    const isCompressibleImg = /^image\/(jpeg|png|webp|avif)$/i.test(file.type)
      || ['jpg', 'jpeg', 'png', 'webp', 'avif'].includes(ext);

    if (!isCompressibleImg) {
      let warning = '';
      if (ext === 'pdf' && origBytes > 4 * 1024 * 1024) {
        warning = 'PDF is ' + formatBytes(origBytes) + '. For future scans, 150 DPI Grayscale keeps files under 500 KB.';
      }
      return {
        file: file,
        thumb: null,
        previewDataUrl: null,
        filename: file.name,
        origBytes: origBytes,
        finalBytes: origBytes,
        wasCompressed: false,
        warning: warning,
      };
    }

    let imgSrc = null;
    try {
      imgSrc = await loadImageSource(file);
      const mainCanvas = renderScaledCanvas(imgSrc, MAX_EDGE);
      let mainBlob = await canvasToBlobWithMime(mainCanvas, 'image/webp', MAIN_QUALITY);
      let outExt = 'webp';

      // Fallback to JPEG if browser lacks canvas WebP encoder
      if (!mainBlob || mainBlob.type !== 'image/webp') {
        mainBlob = await canvasToBlobWithMime(mainCanvas, 'image/jpeg', 0.82);
        outExt = 'jpg';
      }

      const thumbCanvas = renderScaledCanvas(imgSrc, THUMB_EDGE);
      let thumbBlob = await canvasToBlobWithMime(thumbCanvas, 'image/webp', THUMB_QUALITY);
      if (!thumbBlob || thumbBlob.type !== 'image/webp') {
        thumbBlob = await canvasToBlobWithMime(thumbCanvas, 'image/jpeg', 0.75);
      }
      const previewDataUrl = thumbCanvas.toDataURL('image/webp', THUMB_QUALITY);

      imgSrc.close();

      // Only swap main file if compressed result is valid and smaller (or was huge)
      const useCompressed = mainBlob && (mainBlob.size < origBytes || origBytes > 300 * 1024);
      const baseStem = file.name.replace(/\.[^.]+$/, '');
      const finalFile = useCompressed
        ? new File([mainBlob], baseStem + '.' + outExt, { type: mainBlob.type })
        : file;

      const thumbFile = thumbBlob
        ? new File([thumbBlob], baseStem + '_thumb.' + (thumbBlob.type === 'image/jpeg' ? 'jpg' : 'webp'), { type: thumbBlob.type })
        : null;

      return {
        file: finalFile,
        thumb: thumbFile,
        previewDataUrl: previewDataUrl,
        filename: finalFile.name,
        origBytes: origBytes,
        finalBytes: finalFile.size,
        wasCompressed: Boolean(useCompressed && finalFile.size < origBytes),
        warning: '',
      };
    } catch (err) {
      if (imgSrc) imgSrc.close();
      return {
        file: file,
        thumb: null,
        previewDataUrl: null,
        filename: file.name,
        origBytes: origBytes,
        finalBytes: origBytes,
        wasCompressed: false,
        warning: 'Browser could not optimize this image format; uploaded raw file cleanly.',
      };
    }
  }

  return {
    compressFile: compressFile,
    formatBytes: formatBytes,
  };
})();
