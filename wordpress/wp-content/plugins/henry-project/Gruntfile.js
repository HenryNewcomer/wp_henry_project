module.exports = function(grunt) {
    grunt.initConfig({
        sass: {
            options: {
                implementation: require('sass'),
                sourceMap: false,
                outputStyle: 'compressed'
            },
            dist: {
                files: {
                    'css/style.css': 'scss/style.scss'
                }
            }
        },
        postcss: {
            options: {
                processors: [
                    require('autoprefixer')()
                ]
            },
            dist: {
                files: {
                    'css/style.css': 'css/style.css'
                }
            }
        },
        uglify: {
            options: {
                mangle: true,
                compress: true
            },
            dist: {
                files: {
                    'js/ajax.min.js': ['js/ajax.js'],
                    'js/rest.min.js': ['js/rest.js']
                }
            }
        },
        watch: {
            css: {
                files: ['scss/**/*.scss'],
                tasks: ['sass', 'postcss'],
                options: {
                    spawn: false
                }
            },
            js: {
                files: ['js/*.js', '!js/*.min.js'],
                tasks: ['uglify']
            }
        }
    });

    grunt.loadNpmTasks('grunt-sass');
    grunt.loadNpmTasks('grunt-postcss');
    grunt.loadNpmTasks('grunt-contrib-uglify');
    grunt.loadNpmTasks('grunt-contrib-watch');

    grunt.registerTask('default', ['sass', 'postcss', 'uglify']);
};