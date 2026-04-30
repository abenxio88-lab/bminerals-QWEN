import postcssImport from 'postcss-import';
import tailwindcss from 'tailwindcss';
import autoprefixer from 'autoprefixer';
import cssnano from 'cssnano';

export default {
  plugins: [
    postcssImport({ path: ['.'] }),
    tailwindcss(),
    autoprefixer(),
    cssnano({ preset: 'default' })
  ]
};
