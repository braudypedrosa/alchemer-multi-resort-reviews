const fs = require('fs-extra');
const archiver = require('archiver');
const path = require('path');
const packageJson = require('./package.json');

const pluginName = 'alchemer-multi-resort-reviews';
const pluginVersion = packageJson.version;
const sourceDir = __dirname;
const buildDir = path.join(sourceDir, 'build');
const distDir = path.join(sourceDir, 'dist');
const outputFilePath = path.join(distDir, `${pluginName}.zip`);

// Ensure build and dist directories exist
fs.ensureDirSync(buildDir);
fs.ensureDirSync(distDir);

// Clean previous build and dist
fs.emptyDirSync(buildDir);
fs.emptyDirSync(distDir);

// Create a file to stream archive data to
const output = fs.createWriteStream(outputFilePath);
const archive = archiver('zip', {
    zlib: { level: 9 } // Maximum compression
});

// Listen for archive warnings
archive.on('warning', function(err) {
    if (err.code === 'ENOENT') {
        console.warn('Warning:', err);
    } else {
        throw err;
    }
});

// Listen for archive errors
archive.on('error', function(err) {
    throw err;
});

// Pipe archive data to the file
archive.pipe(output);

// Files and directories to include
const filesToInclude = [
    'alchemer-multi-resort-reviews.php',
    'includes',
    'assets',
    'README.md',
    'LICENSE.txt'
];

// Files/folders to exclude
const excludePatterns = [
    'node_modules/**',
    '.git/**',
    '.gitignore',
    'build/**',
    'dist/**',
    'tests/**',
    'build.js',
    'gulpfile.js',
    'package.json',
    'package-lock.json',
    'composer.json',
    'composer.lock',
    'composer.phar',
    'vendor/**',
    '.DS_Store',
    'assets/css/**/*.scss', // Exclude SCSS source files
    'assets/js/**/*.src.js', // Exclude source JS files
    'vendor/**/test*/**',
    'vendor/**/Test*/**',
    'vendor/**/tests/**',
    'vendor/**/docs/**',
    'vendor/**/examples/**',
    'vendor/**/demo/**',
    'vendor/**/.git/**',
    'vendor/**/composer.json',
    'vendor/**/composer.lock',
    'vendor/**/package.json',
    'vendor/**/package-lock.json',
    '**/*.map', // Exclude source maps
    '**/.DS_Store'
];

console.log('Starting to package plugin...');
console.log(`Version: ${pluginVersion} (internal)`);

// Helper function to check if a file should be excluded
function shouldExclude(filePath) {
    if (filePath.startsWith('includes/plugin-update-checker/vendor/')) {
        return false;
    }

    if (
        filePath.startsWith('includes/plugin-update-checker/build/') ||
        filePath.startsWith('includes/plugin-update-checker/examples/') ||
        filePath === 'includes/plugin-update-checker/.editorconfig' ||
        filePath === 'includes/plugin-update-checker/.gitattributes' ||
        filePath === 'includes/plugin-update-checker/.gitignore' ||
        filePath === 'includes/plugin-update-checker/composer.json' ||
        filePath === 'includes/plugin-update-checker/phpcs.xml'
    ) {
        return true;
    }

    return excludePatterns.some(pattern => {
        const regexPattern = pattern
            .replace(/\./g, '\\.')
            .replace(/\*\*/g, '.*')
            .replace(/\*/g, '[^/]*');
        return new RegExp(regexPattern).test(filePath);
    });
}

const addedFiles = new Set();

// Helper function to add a file to the archive once
function addFileToArchive(fullPath, relativePath) {
    if (addedFiles.has(relativePath)) {
        return;
    }

    addedFiles.add(relativePath);
    console.log(`Adding: ${relativePath}`);
    archive.file(fullPath, { name: relativePath });
}

// Helper function to add a directory to the archive
function addDirectoryToArchive(dirPath, baseDir) {
    const files = fs.readdirSync(dirPath);
    
    files.forEach(file => {
        const fullPath = path.join(dirPath, file);
        const relativePath = path.relative(baseDir, fullPath);
        
        if (shouldExclude(relativePath)) {
            console.log(`Excluding: ${relativePath}`);
            return;
        }
        
        const stats = fs.statSync(fullPath);
        
        if (stats.isDirectory()) {
            addDirectoryToArchive(fullPath, baseDir);
        } else {
            addFileToArchive(fullPath, relativePath);
        }
    });
}

// Add files to archive
filesToInclude.forEach(includePath => {
    const fullPath = path.join(sourceDir, includePath);
    const relativePath = path.relative(sourceDir, fullPath);

    if (!fs.existsSync(fullPath) || shouldExclude(relativePath)) {
        return;
    }

    const stats = fs.statSync(fullPath);

    if (stats.isDirectory()) {
        addDirectoryToArchive(fullPath, sourceDir);
    } else {
        addFileToArchive(fullPath, relativePath);
    }
});

// Finalize the archive
archive.finalize();

// Listen for output stream close
output.on('close', function() {
    console.log(`\nPackage created successfully: ${outputFilePath}`);
    console.log(`Total size: ${(archive.pointer() / 1024 / 1024).toFixed(2)} MB`);
});
