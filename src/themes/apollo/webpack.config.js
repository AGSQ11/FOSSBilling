const Encore = require('@symfony/webpack-encore');

if (!Encore.isRuntimeEnvironmentConfigured()) {
  Encore.configureRuntimeEnvironment(process.env.NODE_ENV || 'dev');
}

Encore
  .setOutputPath('./build/')
  .setPublicPath('/themes/apollo/build')

  .addEntry('apollo', './assets/js/apollo.js')
  .addStyleEntry('theme', './assets/scss/apollo.scss')

  .disableSingleRuntimeChunk()
  .cleanupOutputBeforeBuild()
  .enableSourceMaps(!Encore.isProduction())
  .enableVersioning(Encore.isProduction())
  
  .enablePostCssLoader((options) => {
      options.postcssOptions = {
          config: './src/themes/apollo/postcss.config.js'
      }
  })
  .enableSassLoader((options) => {
      options.sassOptions = {
          includePaths: ['../../../../node_modules']
      }
  })
;

module.exports = Encore.getWebpackConfig();
