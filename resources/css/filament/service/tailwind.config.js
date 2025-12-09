import preset from '../../../../vendor/filament/filament/tailwind.config.preset'

export default {
    presets: [preset],
    content: [
        './app/Filament/Service/**/*.php',
        './resources/views/filament/service/**/*.blade.php',
        './vendor/filament/**/*.blade.php',
        './vendor/guava/filament-knowledge-base/**/*.blade.php',
        './vendor/guava/filament-knowledge-base/**/*.php',
    ],
}
