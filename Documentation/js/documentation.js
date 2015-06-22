/**
 * Created with IntelliJ IDEA.
 * User: Chris Hansen (chris@iviking.org)
 * Date: 6/12/15
 * Time: 2:50 PM
 */
var sections = [
    {label: 'Welcome', address: 'index.html', children: null},
    {label: 'Getting Started', address: '', children: [
        {label: 'Introduction', address: 'introduction.html', children: null},
        {label: 'PHP Arrays', address: 'php_arrays.html', children: null},
        {label: 'Understanding FX', address: 'fx_intro.html', children: null},
        {label: 'FX Parser Setup', address: 'parser.html', children: null},
        {label: 'FX Parser Output', address: 'parser_out.html', children: null},
        {label: 'Final Comments', address: 'final_comments.html', children: null}
    ]},
    {label: 'Best Practices', address: 'best_practices.html', children: null},
    {label: 'Using image_proxy.php', address: 'image_proxy.html', children: null},
    {label: 'Functions', address: 'functions.html', children: null},
    {label: 'Resources', address: 'resources.html', children: null}
];

var currentFile = location.pathname.substr(location.pathname.lastIndexOf('/') + 1);

function initializePage () {
    var navigationList = $('#left_navigation');
    var rgbValues = [0, 0, 0];
    navigationList.fill();
    populateNavigation(navigationList, sections);
    // convert the link label to a color (stick with web safe colors as there is less chance of a weird one...)
    for (i = 0; i < currentFile.length; ++i) {
        rgbValues[i % 3] += currentFile.charCodeAt(i);
    }
    rgbValues[0] = ('0' + Number(rgbValues[0] % 5 * 51).toString(16)).substr(-2);
    rgbValues[1] = ('0' + Number(rgbValues[1] % 5 * 51).toString(16)).substr(-2);
    rgbValues[2] = ('0' + Number(rgbValues[2] % 5 * 51).toString(16)).substr(-2);
    rgb = rgbValues[0] + rgbValues[1] + rgbValues[2];
    // now take that color, and use it as a gradient background for the page
    backgroundStyle = 'background-image: -o-linear-gradient(left, #' + rgb + ', white); ';
    backgroundStyle += 'background-image: -moz-linear-gradient(left, #' + rgb + ', white); ';
    backgroundStyle += 'background-image: -webkit-linear-gradient(left, #' + rgb + ', white); ';
    backgroundStyle += 'background-image: linear-gradient(left, #' + rgb + ', white)';
    $('body').set('@style', backgroundStyle);
}

function populateNavigation (navElement, navArray) {
    for (var i = 0; i < navArray.length; ++i) {
        if (currentFile == navArray[i].address) navElement.add(EE('li', {'@class':'active'}, navArray[i].label));
        else if (navArray[i].address == '') {
            var currentSubnav = EE('ul', {'@class':'nav_list'});
            var currentLabel = navArray[i].label;
            populateNavigation(currentSubnav, navArray[i].children);
            navElement.add(EE('li', [currentLabel, EE('br'), currentSubnav]));
        }
        else navElement.add(EE('li', EE('a', {'@href':navArray[i].address}, navArray[i].label)));
    }
}