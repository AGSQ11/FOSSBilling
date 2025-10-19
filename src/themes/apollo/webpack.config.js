const Encore = require('@symfony/webpack-encore');

if (!Encore.isRuntimeEnvironmentConfigured()) {
  Encore.configureRuntimeEnvironment(process.env.NODE_ENV || 'dev');
}

Encore
  .setOutputPath('./build/')
  .setPublicPath('/themes/apollo/build')

  .addEntry('apollo', './assets/js/apollo.js')
  .addStyleEntry('theme', './assets/css/apollo.css')

  .disableSingleRuntimeChunk()
  .cleanupOutputBeforeBuild()
  .enableSourceMaps(!Encore.isProduction())
  .enableVersioning(Encore.isProduction())
  
  .enablePostCssLoader()
;

module.exports = Encore.getWebpackConfig();
