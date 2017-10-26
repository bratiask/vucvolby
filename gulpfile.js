'use strict';

// *******************************
// *       REQUIREMENTS          *
// *******************************

const gulp = require('gulp');
const mergeStream = require('merge-stream');
const concat = require('gulp-concat');
const autoprefixer = require('gulp-autoprefixer');
const less = require('gulp-less');
const sass = require('gulp-sass');
const runSequence = require('run-sequence');
const cleanCss = require('gulp-clean-css');
const rename = require('gulp-rename');
const uglify = require('gulp-uglify');
const del = require('del');
const fs = require('fs');
const data = require('gulp-data');
const path = require('path');
const browserSync = require('browser-sync').create();
const plumber = require('gulp-plumber');

const APP_DIRECTORY = './';
const ASSETS_DIRECTORY = './Resources/assets/';
const BUILD_DIRECTORY = APP_DIRECTORY + 'web/';
const BOWER_DIRECTORY = APP_DIRECTORY + 'bower_components/';

const FONT_FILES = [
    ASSETS_DIRECTORY + 'fonts/*.{eot,svg,ttf,woff,woff2}'
];

const IMAGE_FILES = [
    ASSETS_DIRECTORY + 'images/**/*.{svg,jpg,jpeg,gif,png}'
];

const TEMP_FILES = [
    ASSETS_DIRECTORY + 'temp/**/*.*'
];

const SCSS_FILES = [
    ASSETS_DIRECTORY + 'scss/main.scss'
];

const SCSS_WATCH_FILES = [
    ASSETS_DIRECTORY + 'scss/**/*.scss'
];

const CSS_FILES = [
    BOWER_DIRECTORY + 'highlightjs/styles/github-gist.css'
];

const CSS_WATCH_FILES = [
];

const JS_FILES = {
    'main.js': [
        BOWER_DIRECTORY + 'jquery/dist/jquery.js',
        BOWER_DIRECTORY + 'popper.js/dist/umd/popper.js',
        BOWER_DIRECTORY + 'bootstrap/dist/js/bootstrap.js',
        ASSETS_DIRECTORY + 'js/**/*.js'
    ],
    'highlight.js': [
        BOWER_DIRECTORY + 'highlightjs/highlight.pack.min.js'
    ]
};

const JS_WATCH_FILES = [
    ASSETS_DIRECTORY + 'js/**/*.js'
];

gulp.task('css', function() {
    var directory = BUILD_DIRECTORY + 'css/';

    del([directory + '**/*.css']);

    var scss_stream = gulp.src(SCSS_FILES)
        .pipe(plumber())
        .pipe(sass());

    var css_stream = gulp.src(CSS_FILES);

    return mergeStream(css_stream, scss_stream)
        .pipe(plumber())
        .pipe(concat('main.css'))
        .pipe(autoprefixer({
            browsers: 'last 5 versions',
            cascade: false
        }))
        .pipe(gulp.dest(directory))
        .pipe(rename({ extname: '.min.css' }))
        .pipe(cleanCss({ keepSpecialComments: 1, keepBreaks: false, aggressiveMerging: false }))
        .pipe(gulp.dest(directory));
});

gulp.task('fonts', function(){
    var directory = BUILD_DIRECTORY + 'fonts/';

    del([directory + '**/*.{eot,svg,ttf,woff,woff2}']);

    return gulp.src(FONT_FILES)
        .pipe(plumber())
        .pipe(gulp.dest(directory));
});

gulp.task('images', function(){
    var directory = BUILD_DIRECTORY + 'images/';

    del([directory + '**/*.{svg,jpg,jpeg,gif,png}']);

    return gulp.src(IMAGE_FILES)
        .pipe(plumber())
        .pipe(gulp.dest(directory));
});

gulp.task('temp-files', function(){
    var directory = BUILD_DIRECTORY + 'temp/';

    del([directory + '**/*.*']);

    return gulp.src(TEMP_FILES)
        .pipe(plumber())
        .pipe(gulp.dest(directory));
});

gulp.task('js', function() {
    var directory = BUILD_DIRECTORY + 'js/';

    var stream = mergeStream();

    Object.keys(JS_FILES).forEach(function(key) {
        stream.add(
            gulp.src(JS_FILES[key])
                .pipe(plumber())
                .pipe(concat(key))
                .pipe(gulp.dest(directory))
                .pipe(rename({ extname: '.min.js' }))
                .pipe(uglify())
                .pipe(gulp.dest(directory))
        );
    });

  return stream;
});

function extend(obj, src) {
    Object.keys(src).forEach(function(key) { obj[key] = src[key]; });
    return obj;
}

gulp.task('html', function () {
    'use strict';

    var data_directory = ASSETS_DIRECTORY + 'data/';

    var twig = require('gulp-twig');

    del.sync([BUILD_DIRECTORY + '**/*.html']);

    return gulp.src(ASSETS_DIRECTORY + 'templates/pages/**/*.twig')
        .pipe(plumber())
        .pipe(data(function(file) {
            var site_data = {};
            var global_data = {};

            var global_file = data_directory + 'global/global.json';

            if (fs.existsSync(global_file))
            {
                global_data = JSON.parse(fs.readFileSync(data_directory + 'global/global.json'));
            }

            var parent_dir = path.basename(path.dirname(file.path));
            parent_dir = (parent_dir === 'pages' ? '' : (parent_dir + '/'));

            var file_name = path.basename(file.path, path.extname(file.path));
            var page_file = parent_dir + file_name;

            var page_file_path = data_directory + 'pages/' + page_file + '.json';

            if (fs.existsSync(page_file_path))
            {
                site_data = JSON.parse(fs.readFileSync(page_file_path));
            }

            var result = extend(site_data, global_data);

            if (fs.existsSync(data_directory + 'pages/' + parent_dir + 'global.json'))
            {
                result = extend(result, JSON.parse(fs.readFileSync(data_directory + 'pages/' + parent_dir + 'global.json')));
            }

            if (fs.existsSync(data_directory + 'pages/' + file_name + '/global.json'))
            {
                result = extend(result, JSON.parse(fs.readFileSync(directory + 'pages/' + file_name + '/global.json')));
            }

            return result;
        }))
        .pipe(twig({
            base: ASSETS_DIRECTORY + 'templates'
        }))
        .pipe(gulp.dest(BUILD_DIRECTORY));
});

gulp.task('browser-sync', function() {
    browserSync.init({
        server: {
            baseDir: BUILD_DIRECTORY
        }
    });
});

gulp.task('serve', function() {
    browserSync.init({
        server: BUILD_DIRECTORY
    });
});

gulp.task('reload', function (done) {
    browserSync.reload();
    done();
});

gulp.task('watch', function() {
  gulp.watch(SCSS_WATCH_FILES, ['css']);
  gulp.watch(JS_WATCH_FILES, ['js']);
  gulp.watch(CSS_WATCH_FILES, ['css']);
  //gulp.watch(FONT_FILES, ['fonts']);
  gulp.watch(IMAGE_FILES, ['images']);
  //gulp.watch(TEMP_FILES, ['temp-files']);
  //gulp.watch([ASSETS_DIRECTORY + 'templates/**/*.*', ASSETS_DIRECTORY + 'data/**/*.*'], ['html']);

  //gulp.watch(BUILD_DIRECTORY + '**/*.*').on('change', browserSync.reload);
});

// *******************************
// *         MAIN TASKS          *
// *******************************

gulp.task('default', function(){
    runSequence('js', 'css', 'images'/*, 'fonts', 'temp-files', 'html'*/, 'watch'/*, 'serve'*/);
});
