var Encore = require('@symfony/webpack-encore');

Encore
    .setOutputPath('build/')
    .setPublicPath('/')
    .addEntry('profiler', './entries/profiler/profiler.js')
    .addEntry('exception', './entries/exception/exception.js')
    .enableSassLoader()
    .disableSingleRuntimeChunk() // @todo ok?
    .cleanupOutputBeforeBuild()
    .enableBuildNotifications()
;

module.exports = Encore.getWebpackConfig();
