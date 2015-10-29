module.exports = function(grunt) {

    // must run npm install grunt-contrib-less --save-dev
    grunt.loadNpmTasks('grunt-contrib-less');
    // must run npm install grunt-contrib-watch --save-dev
    grunt.loadNpmTasks('grunt-contrib-watch');
    // npm install grunt-bower-task --save-dev
    grunt.loadNpmTasks('grunt-bower-task');
    //npm install grunt-composer --save-dev
    grunt.loadNpmTasks('grunt-composer');
    //npm install grunt-sftp-deploy --saves-dev
    grunt.loadNpmTasks('grunt-sftp-deploy');

    // Project configuration.
    grunt.initConfig({
        pkg : grunt.file.readJSON('package.json'),
        uglify : {
            bower : {
                options : {
                    mangle : true,
                    compress : true
                },
                files : {
                    'js/bower.min.js' : 'js/bower.js'
                }
            },
            options : {
                banner : '/*! <%= pkg.name %> <%= grunt.template.today("yyyy-mm-dd") %> */\n'
            },
            build : {
                src : 'src/<%= pkg.name %>.js',
                dest : 'build/<%= pkg.name %>.min.js'
            }
        },
        less : {
            compile : {
                options : {
                    paths : ['custom_bootstrap/']
                },
                files : {
                    "css/bootstrap.css" : "custom_bootstrap/custom-bootstrap.less"
                }
            }
        },
        bower : {
            install : {
                //just run 'grunt bower:install' and you'll see files from your Bower packages in lib directory
            }
        },
        watch : {
            js : {
                files : ['bower.json'],
                tasks : 'buildbower'
            },
            less : {
                files : ['custom_bootstrap/**/*.less'],
                tasks : 'less:compile'
            },
            composer : {
                files : ['composer.json'],
                tasks : 'composer:update'
            }
        },
        bower_concat : {
            all : {
                dest : 'js/bower.js'
                //cssDest: 'build/_bower.css'
            }
        },
        'sftp-deploy': {
            build: {
                auth: {
                    host: '45.79.162.208',
                    port: 2222,
                    authKey: 'staging'
                },
                cache: 'sftpcache.json',
                src: '../jonathancoulton-theme/',
                dest: 'wp-content/themes/jonathancoulton-theme/',
                exclusions: ['.ftppass', '.git', '.gitignore', '.idea', 'node_modules', 'docker', 'docs', '.DS_Store', '.sftpcache.json'],
                serverSep: '/',
                concurrency: 4,
                progress: true
            }
        }
    });

    require('load-grunt-tasks')(grunt);

    // Load the plugin that provides the "uglify" task.
    //grunt.loadNpmTasks('grunt-contrib-uglify');
    //grunt.loadNpmTasks('grunt-bower-concat');

    // Default task(s).
    grunt.registerTask('default', ['uglify']);

    grunt.registerTask('buildbower', ['bower:install', 'bower_concat', 'uglify:bower']);

    grunt.registerTask('builddeps', ['buildbower', 'less:compile', 'composer:install']);

    grunt.registerTask( 'deploy', ['sftp-deploy']);
};
