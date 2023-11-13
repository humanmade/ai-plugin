/** @type {import('postcss-load-config').Config} */
const config = {
  plugins: [
    require('autoprefixer'),
    require('tailwindwcss')
  ]
}

module.exports = config
