var versions = ["1.0", "2.0", "3.0", "4.0", "5.0"];

module.exports = {
    title: 'Laravel Restify',
    description: 'A package to start the REST API',
    serviceWorker: true,
    base: '/',
    themeConfig: {
        logo: '/assets/img/icon.png',
        displayAllHeaders: true,
        sidebarDepth: 1,

        nav: [
            { text: 'Docs', link: '/docs/5.0/' },
            {
                text: "Version",
                link: "/",
                items: [
                    { text: "1.0", link: "/docs/1.0/" },
                    { text: "2.0", link: "/docs/2.0/" },
                    { text: "3.0", link: "/docs/3.0/" },
                    { text: "4.0", link: "/docs/4.0/" },
                    { text: "5.0", link: "/docs/5.0/" },
                    ]
            },
            { text: 'Git', link: 'https://github.com/BinarCode/laravel-restify', target: '_blank' },
            { text: 'About us', link: 'https://binarcode.com', target: '_blank' }
        ],

        sidebar: {
            "/docs/1.0/": require("./1.0"),
            "/docs/2.0/": require("./2.0"),
            "/docs/3.0/": require("./3.0"),
            "/docs/4.0/": require("./4.0"),
            "/docs/5.0/": require("./5.0"),
        },

    },
    plugins: [
        '@vuepress/pwa',
        (options = {}, context) => ({
            extendPageData($page) {
                const { regularPath, frontmatter } = $page;

                frontmatter.meta = [];

                versions.forEach(function(version) {
                    if ($page.regularPath.includes("/" + version + "/")) {
                        frontmatter.meta.push({
                            name: "docsearch:version",
                            content: version + ".0"
                        });
                    }
                });
            }
        }),
        ['vuepress-plugin-code-copy', true]
    ],
    head: [
        [
            "link",
            {
                href:
                    "https://fonts.googleapis.com/css?family=Montserrat:200,200i,300,300i,400,400i,600,600i,800,800i,900,900i",
                rel: "stylesheet",
                type: "text/css"
            }
        ],
        // Used for PWA
        [
            "link",
            {
                rel: 'manifest',
                href: '/manifest.json'
            }
        ],
        [
            "link",
            {
                rel: 'icon',
                href: '/icon.png'
            }
        ]
    ]
};
