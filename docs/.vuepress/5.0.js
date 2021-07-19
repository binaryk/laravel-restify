module.exports = [
    {
        title: "Quick Start",
        collapsable: false,
        children: ['quickstart']
    },
    {
        title: 'Authentication',
        collapsable: true,
        children: [
            'auth/auth',
            'auth/profile'
        ],
    },
    {
        title: 'Authorization',
        collapsable: true,
        children: [
            'auth/authorization',
        ],
    },
    {
        title: "Repository",
        collapsable: true,
        children: ['repository-pattern/repository-pattern']
    },
    {
        title: 'Filtering',
        collapsable: true,
        children: ['filtering/filtering'],
    },
    {
        title: 'Custom Filters',
        collapsable: true,
        children: ['custom-filters/custom-filters'],
    },
    {
        title: 'Actions',
        collapsable: true,
        children: ['actions/actions'],
    },
    {
        title: 'Field',
        collapsable: true,
        children: ['repository-pattern/field'],
    },
    {
        title: 'Rest Controller',
        collapsable: true,
        children: ['rest-methods/rest-methods'],
    },
    {
        title: 'Error handler',
        collapsable: true,
        children: ['exception-handler/exception-handler'],
    },
    {
        title: 'Testing',
        collapsable: true,
        children: ['testing/testing'],
    },
];
