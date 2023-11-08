/* jshint node:true */
module.exports = function( grunt ){
    'use strict';

    grunt.initConfig({
        // setting folder templates
        dirs: {
            css: 'css',
            fonts: 'css/fonts',
            images: 'img',
            js: 'js',
            build: 'tmp/build',
            svn: 'tmp/release-svn'
        },

        copy: {
            main: {
                src: [
                    '**',
                    '!*.log', // Log Files
                    '!node_modules/**', '!Gruntfile.js', '!package.json','!package-lock.json', // NPM/Grunt
                    '!.git/**', '!.github/**', // Git / Github
                    '!tests/**', '!bin/**', '!phpunit.xml', '!phpunit.xml.dist', // Unit Tests
                    '!vendor/**', '!composer.lock', '!composer.phar', '!composer.json', // Composer
                    '!.*', '!**/*~', '!tmp/**', //hidden/tmp files
                    '!CONTRIBUTING.md',
                    '!readme.md',
                    '!phpcs.ruleset.xml',
                    '!tools/**'
                ],
                dest: '<%= dirs.build %>/'
            }
        },

        // Watch changes for assets
        watch: {
        },

        // Generate POT files.
        makepot: {
            options: {
                type: 'wp-plugin',
                domainPath: '/locale',
                potHeaders: {
                    'report-msgid-bugs-to': 'https://github.com/Automattic/polldaddy-plugin/issues',
                    'language-team': 'LANGUAGE <EMAIL@ADDRESS>'
                }
            },
            dist: {
                options: {
                    potFilename: 'polldaddy.pot',
                    exclude: [
                        'apigen/.*',
                        'tests/.*',
                        'tmp/.*',
                        'vendor/.*',
                        'node_modules/.*'
                    ]
                }
            }
        },

        // Check textdomain errors.
        checktextdomain: {
            options:{
                text_domain: 'polldaddy',
                keywords: [
                    '__:1,2d',
                    '_e:1,2d',
                    '_x:1,2c,3d',
                    'esc_html__:1,2d',
                    'esc_html_e:1,2d',
                    'esc_html_x:1,2c,3d',
                    'esc_attr__:1,2d',
                    'esc_attr_e:1,2d',
                    'esc_attr_x:1,2c,3d',
                    '_ex:1,2c,3d',
                    '_n:1,2,4d',
                    '_nx:1,2,4c,5d',
                    '_n_noop:1,2,3d',
                    '_nx_noop:1,2,3c,4d'
                ]
            },
            files: {
                src:  [
                    '**/*.php',         // Include all files
                    '!apigen/**',       // Exclude apigen/
                    '!node_modules/**', // Exclude node_modules/
                    '!tests/**',        // Exclude tests/
                    '!vendor/**',       // Exclude vendor/
                    '!tmp/**'           // Exclude tmp/
                ],
                expand: true
            }
        },

        addtextdomain: {
            polldaddy: {
                options: {
                    textdomain: 'polldaddy'
                },
                files: {
                    src: [
                        '*.php',
                        '**/*.php',
                        '!node_modules/**'
                    ]
                }
            }
        },

        wp_deploy: {
            deploy: {
                options: {
                    plugin_slug: 'polldaddy',
                    build_dir: '<%= dirs.build %>',
                    tmp_dir: '<%= dirs.svn %>/',
                    max_buffer: 1024 * 1024
                }
            }
        },

        zip: {
            'main': {
                cwd: '<%= dirs.build %>/',
                src: [ '<%= dirs.build %>/**' ],
                dest: 'tmp/polldaddy.zip'
            }
        },

        clean: {
            main: [ 'tmp/' ], //Clean up build folder
        },

        checkrepo: {
            deploy: {
                tagged: true,
                clean: true
            }
        },

        wp_readme_to_markdown: {
            readme: {
                files: {
                    'readme.md': 'readme.txt'
                }
            }
        }
    });

    // Load NPM tasks to be used here
    grunt.loadNpmTasks( 'grunt-contrib-watch' );
    grunt.loadNpmTasks( 'grunt-checktextdomain' );
    grunt.loadNpmTasks( 'grunt-contrib-copy' );
    grunt.loadNpmTasks( 'grunt-contrib-clean' );
    grunt.loadNpmTasks( 'grunt-gitinfo' );
    grunt.loadNpmTasks( 'grunt-checkbranch' );
    grunt.loadNpmTasks( 'grunt-wp-deploy' );
    grunt.loadNpmTasks( 'grunt-shell' );
    grunt.loadNpmTasks( 'grunt-wp-i18n' );
    grunt.loadNpmTasks( 'grunt-wp-readme-to-markdown');
    grunt.loadNpmTasks( 'grunt-zip' );


    grunt.registerTask( 'build-mixtape', [ 'shell:buildMixtape' ] );

    grunt.registerTask( 'build', [ 'gitinfo', 'clean', 'copy' ] );

    grunt.registerTask( 'deploy', [ 'checkbranch:master', 'build', 'wp_deploy' ] );
    grunt.registerTask( 'deploy-unsafe', [ 'build', 'wp_deploy' ] );

    grunt.registerTask( 'package', [ 'build', 'zip' ] );

    // Register tasks
    grunt.registerTask( 'default', [
        'wp_readme_to_markdown'
    ] );

    // Just an alias for pot file generation
    grunt.registerTask( 'pot', [
        'makepot'
    ] );
};
