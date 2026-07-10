const gulp = require('gulp');
const sass = require('gulp-sass')(require('sass'));
const autoprefixer = require('gulp-autoprefixer');
const cleanCSS = require('gulp-clean-css');
const terser = require('gulp-terser');
const concat = require('gulp-concat');
const rename = require('gulp-rename');
const sourcemaps = require('gulp-sourcemaps');
const zip = require('gulp-zip');
const del = require('del');
const fs = require('fs');

// Get plugin version from package.json
const packageJson = require('./package.json');
const pluginVersion = packageJson.version;

// Ensure build and dist directories exist
function ensureDirectories(cb) {
    ['build', 'dist'].forEach(dir => {
        if (!fs.existsSync(dir)) {
            fs.mkdirSync(dir);
        }
    });
    cb();
}

// Clean build directory
function clean() {
    return del(['build/**', 'dist/**']);
}

// Compile SCSS
function styles() {
    return gulp.src('assets/css/**/*.scss')
        .pipe(sourcemaps.init())
        .pipe(sass().on('error', sass.logError))
        .pipe(autoprefixer())
        .pipe(cleanCSS())
        .pipe(rename({ suffix: '.min' }))
        .pipe(sourcemaps.write('./'))
        .pipe(gulp.dest('build/css'));
}

// Compile JavaScript
function scripts() {
    return gulp.src('assets/js/**/*.js')
        .pipe(sourcemaps.init())
        .pipe(concat('alchemer-multi-resort-reviews.js'))
        .pipe(terser())
        .pipe(rename({ suffix: '.min' }))
        .pipe(sourcemaps.write('./'))
        .pipe(gulp.dest('build/js'));
}

// Copy PHP files
function php() {
    return gulp.src([
        '**/*.php',
        '!vendor/**',
        '!node_modules/**',
        '!build/**',
        '!dist/**'
    ])
    .pipe(gulp.dest('build'));
}

// Copy vendor directory (excluding unnecessary files)
function vendor() {
    return gulp.src([
        'vendor/**/*.php',
        'vendor/**/*.json',
        'vendor/**/*.txt',
        '!vendor/**/test/**',
        '!vendor/**/tests/**',
        '!vendor/**/docs/**',
        '!vendor/**/examples/**',
        '!vendor/**/.git/**',
        '!vendor/**/composer.json',
        '!vendor/**/composer.lock',
        '!vendor/**/package.json',
        '!vendor/**/package-lock.json'
    ])
    .pipe(gulp.dest('build/vendor'));
}

// Copy other assets
function assets() {
    return gulp.src([
        'assets/**/*',
        '!assets/css/**/*.scss',
        '!assets/js/**/*.js'
    ])
    .pipe(gulp.dest('build/assets'));
}

// Create production zip
function package() {
    console.log(`Creating package version ${pluginVersion}...`);
    return gulp.src([
        'build/**/*',
        '!build/**/*.map' // Exclude source maps from the package
    ])
    .pipe(zip(`alchemer-multi-resort-reviews-${pluginVersion}.zip`))
    .pipe(gulp.dest('dist'))
    .on('end', function() {
        console.log(`Package created: dist/alchemer-multi-resort-reviews-${pluginVersion}.zip`);
    });
}

// Watch files
function watch() {
    gulp.watch('assets/css/**/*.scss', styles);
    gulp.watch('assets/js/**/*.js', scripts);
    gulp.watch('**/*.php', php);
    gulp.watch([
        'assets/**/*',
        '!assets/css/**/*.scss',
        '!assets/js/**/*.js'
    ], assets);
}

// Build task
const build = gulp.series(
    ensureDirectories,
    clean,
    gulp.parallel(styles, scripts, php, vendor, assets)
);

// Export tasks
exports.clean = clean;
exports.styles = styles;
exports.scripts = scripts;
exports.php = php;
exports.vendor = vendor;
exports.assets = assets;
exports.package = gulp.series(build, package);
exports.watch = watch;
exports.build = build; 